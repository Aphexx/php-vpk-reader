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