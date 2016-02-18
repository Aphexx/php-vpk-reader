<?php
namespace VPKReader;

use VPKReader\VPKHeader;
use VPKReader\VPKDirectoryEntry;
use VPKReader\VPKFile;
use VPKReader\Exception;


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
        if(!$this->vpk_fd)
            throw new Exception("File '$vpk_path' open failed");
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
        $res = '';
        $f = $this->get_entry($path);
        if(!$f)
            throw new Exception("Can't find valid entry at path: '$path'");
        if($f->data_size == 0)
            return $res;
        if($offset >= $f->size)
            throw new Exception('Offset exceeds file size');
        if($f->preload_size > 0 && $offset < $f->preload_size) {
            $readsize = min($size, $f->preload_size);
            fseek($this->vpk_fd, $f->PreloadOffset + $offset);
            $res = fread($this->vpk_fd, $readsize);
            if(!$res)
                throw new Exception("$path: preload read failed $readsize");
        }else if($f->data_size > 0) {
            $fd = $this->archive_fds[$f->archive_index];
            $readsize = min($size, $f->data_size);
            fseek($fd, $f->data_offset+$offset);
            $res = fread($fd, $readsize);
            if(!$res)
                throw new Exception("IOE");
        }else{
            throw Exception("Not implemented: unknown error");
        }

        return $res;
    }

    protected function read_archive($fd){
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

    protected function open_vpk_data_archive($id){
        $fn = $this->vpk_path;
        if(!preg_match('/^.+_dir.vpk$/', $fn))
            throw new Exception('Unknown name format: $fn');
        foreach(['%03d', '%02d', '%d'] as $patt){
            $sid = sprintf($patt, $id);
            $res = '';
            $r = preg_replace('/^(.+_)(dir)(.vpk)$/', '${1}' . preg_quote($sid) . '${3}', $fn);
            if(($fd = fopen($r, 'rb'))) return $fd;
        }
        throw new Exception("Error opening data archive");
    }

    protected function open_data_archives(){
        $tc = $this->vpk_fd_count;
        for($i=0; $i < $tc; $i++) {
            $this->archive_fds[$i] = $this->open_vpk_data_archive($i);
        }
    }

    protected static function _read_string($fd){
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
