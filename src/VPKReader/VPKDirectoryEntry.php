<?php
namespace VPKReader;

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