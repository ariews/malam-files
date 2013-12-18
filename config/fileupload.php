                         <?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author  arie
 */

return array(
    'upload'    => array(
        'path'      => DOCROOT.'uploads',
        'accept'    => array(
            'image' => array('jpg', 'jpeg', 'gif', 'png'),
            'audio' => array('mp3', 'oga', 'ogg', 'wav', 'mp4'),
            'video' => array('mp4', 'avi', 'flv', 'ogv', 'webm'),
        ),
        'max'       => '10M',
        'url'       => 'uploads/:file',
    ),
    'thumbnails' => array(
        'name'      => 'th_:widthx:height_:name',
        'sizes'     => array(
//            'small'  => array(
//                'width'  => 260,
//                'height' => 180,
//            ),
//            'medium' => array(
//                'width'  => 300,
//                'height' => 200,
//            ),
//            'big'    => array(
//                'width'  => 600,
//                'height' => 400,
//            ),
        )
    ),
);