<?php
namespace VPKReader;

class VPKFile {
	public
        $size, //uint64
        $preload_size, //uint
        $preload_offset, //uint64
        $archive_index, //ushort
        $data_size, //uint
        $data_offset; //uint64
}