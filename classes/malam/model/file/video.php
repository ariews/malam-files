<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Description of video
 *
 * @author arie
 */

class Malam_Model_File_Video extends Model_File
{
    protected $_is_direct_call  = FALSE;

    public function file_accept()
    {
        $config = $this->_config['upload']['accept'];
        return $config['video'];
    }
}