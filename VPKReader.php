<?php
namespace VPKReader;

class VPKHeader{
	public $signature,
	$version,
	$tree_length;

	public $unknown1, // 0 in CSGO
	$footer_length,
	$unknown3, // 48 in CSGO
	$unknown4; // 0 in CSGO

	function __construct($fd){
		$this->read_header($fd);
		$this->read_header2($fd);
	}

	function read_header($fd){
		$this->signature 	= unpack('I', fread($fd, 4))[1];
		$this->version 		= unpack('I', fread($fd, 4))[1];
		$this->tree_length 	= unpack('I', fread($fd, 4))[1];
	}
	function read_header2($fd){
		$this->Unknown1		= unpack('I', fread($fd, 4))[1];
		$this->FooterLength	= unpack('i', fread($fd, 4))[1];
		$this->Unknown3		= unpack('I', fread($fd, 4))[1];
		$this->Unknown4		= unpack('I', fread($fd, 4))[1];
	}
}

class VPKFile {
	public
	$size, //uint64
	$preload_size, //uint
	$preload_offset, //uint64
	$archive_index, //ushort
	$data_size, //uint
	$data_offset; //uint64
}

class VPKDirectoryEntry {
	public
	$CRC, // A 32bit CRC of the file's data.
	$preload_bytes, // The number of bytes contained in the index file.
	// A zero based index of the archive this file's data is contained in.
	// If 0x7fff, the data follows the directory.
	$archive_index,
	// If ArchiveIndex is 0x7fff, the offset of the file data relative to the end of the directory (see the header for more details).
	// Otherwise, the offset of the data from the start of the specidfied archive.
	$entry_offset,

	// If zero, the entire file is stored in the preload data.
	// Otherwise, the number of bytes stored starting at EntryOffset.
	$entry_length,
	$terminator;

	function read_dir_entry($fd){
		$this->CRC 		= unpack('I', fread($fd, 4))[1];
		$this->preload_bytes 	= unpack('S', fread($fd, 2))[1];
		$this->archive_index 	= unpack('S', fread($fd, 2))[1];
		$this->entry_offset 	= unpack('I', fread($fd, 4))[1];
		$this->entry_length 	= unpack('I', fread($fd, 4))[1];
		$this->terminator 	= unpack('S', fread($fd, 2))[1];
	}
}

class VPK{
	public 
	$vpk_path,
	$vpk_fd,
	$vpk_data_offset,
	$vpk_header,
	$vpk_fd_count,
	$archive_fds = [],
	$vpk_entries = [];


	function __construct($vpk_path){
		$this->vpk_path = $vpk_path;
		$this->vpk_fd = fopen($vpk_path, 'rb');
		$this->vpk_header = new VPKHeader($this->vpk_fd);
		$this->vpk_data_offset = 12 + (($this->vpk_header->version === 2) ? 16 : 0) + $this->vpk_header->tree_length;
		$this->vpk_entries = $this->read_archive($this->vpk_fd);
		$this->open_data_archives();
	}

	function get_entry($path){
		$path = trim($path, '/');
		$pp = explode('/', $path);
		$cur = &$this->vpk_entries;
		foreach($pp as $p){
			$cur = &$cur[$p];
			if(!isset($cur))
				return NULL;
		}
		return $cur;
	}

	function read_file($path, $size, $offset=0){
		$f = $this->get_entry($path);
		if(!$f)
			return NULL;
		if($offset >= $f->size)
			throw new \Exception('Offset exceeds file size');
		$pos = $offset = $end = min($offset+$size, $f->size);
		if($f->preload_size > 0 && $pos < $f->preload_size) {
			$readsize = min($end-$pos, $f->preload_size);
			fseek($this->vpk_fd, $f->PreloadOffset + $pos);
			$res = fread($this->vpk_fd, $size);
			if(!$res)
				throw new \Exception("$path: preload read failed $readsize");
		}
		if($end-$pos >= 0 && $f->data_size > 0) {
			$fd = $this->archive_fds[$f->archive_index];
			$buf = 0;
			$readsize = min($end-$pos, $f->data_size);
			fseek($fd, $f->data_offset+$pos-$offset);
			$res = fread($fd, $f->data_size);
			if(!$res)
				throw new \Exception("IOE");
		}
		return $res;
	}
	
	private function read_archive($fd){
		$dc = [];
		while(true){
			$ext = self::_read_string($fd);
			if($ext === '')
				break;
			while(true){
				$path = self::_read_string($fd);
				if($path === '')
					break;
				while(true){
					$fname = self::_read_string($fd);
					if($fname === '')
						break;
					$dir_ent = new VPKDirectoryEntry;
					$dir_ent->read_dir_entry($fd);

					$offset = fseek($fd, 0, SEEK_CUR);

					$f = new VPKFile();
					$f->size = $dir_ent->preload_bytes + $dir_ent->entry_length;
					$f->preload_size = $dir_ent->preload_bytes;
					$f->preload_offset = ($f->preload_size > 0) ? $offset : 0;
					$f->archive_index = $dir_ent->archive_index;
					if($f->archive_index != 32767 && $f->archive_index >= $this->vpk_fd_count) $this->vpk_fd_count = $f->archive_index+1;
					$f->data_size = $dir_ent->entry_length;
					$f->data_offset = ($f->data_size) ? ((($f->archive_index == 0x7fff) ? $this->vpk_data_offset : 0) + $dir_ent->entry_offset) : 0;

					$dir_tree = explode('/', $path);
					$cur = &$dc;
					foreach($dir_tree as $dir){
						if(!isset($cur[$dir])) $cur[$dir] = [];
						$cur = &$cur[$dir];
					}
					$eext = $ext ? ".$ext" : '';
					$cur["$fname$eext"] = $f;
					if($dir_ent->preload_bytes > 0)
						fseek($fd, $dir_ent->preload_bytes, SEEK_CUR);
					unset($dir_ent);
				}
			}
		}
		return $dc;
	}

	private function open_vpk_data_archive($id){
		$fn = $this->vpk_path;
		if(!preg_match('/^.+_dir.vpk$/', $fn))
			throw new \Exception('Unknown name format: $fn');
		foreach(['%03d', '%02d', '%d'] as $patt){
			$sid = sprintf($patt, $id);
			$res = '';
			$r = preg_replace('/^(.+_)(dir)(.vpk)$/', '${1}' . preg_quote($sid) . '${3}', $fn);
			if(($fd = fopen($r, 'rb'))) return $fd;
		}
		throw new \Exception("Error opening data archive");
	}

	private function open_data_archives(){
		$tc = $this->vpk_fd_count;
		for($i=0; $i < $tc; $i++) {
			$this->archive_fds[$i] = $this->open_vpk_data_archive($i);
		}
	}

	private static function _read_string($fd){
		$buf = '';
		$cnt = 0;
		$c;
		while($cnt < 512){
			$c = fgetc($fd);
			if(ord($c) === 0) break;
			$buf .= $c;
			$cnt++;
		}
		return $buf;
	}
}
