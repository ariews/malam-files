<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Description of image
 *
 * @author arie
 */

class Malam_Model_File_Image extends Model_File
{
    protected $_is_direct_call  = FALSE;

    public function file_accept()
    {
        $config = $this->_config['upload']['accept'];
        return $config['image'];
    }

    public function save_from_post($values)
    {
        $file   = $values['file'];
        $model  = NULL;
        $jq     = FALSE;

        if ($values['fu'] && $values['fumodel'] && $values['fuid'])
        {
            $model = ORM::factory($values['fumodel'])
                    ->find_by_id($values['fuid'])->find();

            $jq  = TRUE;
            $tmp = array();

            foreach ($file as $key => $arr)
            {
                $tmp[$key] = $arr[0];
            }
            $values['file'] = $tmp;
            $file = $tmp;
        }

        $return = parent::save_from_post($values);

        if (FALSE !== $return)
        {
            if ($jq && $model !== NULL && $model->loaded())
            {
                if (! $model->has('images', $return))
                {
                    $model->add('images', $return);
                }
            }
        }

        return $return;
    }

    public function as_ajax_result()
    {
        $res = parent::as_ajax_result();
        $res['files']['thumb'] = $this->thumbnail('small');

        return $res;
    }

    public function thumbnail($key = NULL)
    {
        $this->thumbnail_generator();

        $_config  = $this->_config;
        $path     = $_config['upload']['url'];
        $sizes    = $_config['thumbnails']['sizes'];
        $skeleton = $_config['thumbnails']['name'];

        if (! empty($key) && isset($sizes[$key]))
        {
            $size = $sizes[$key];
        }
        else
        {
            $size = current($sizes);
        }

        return URL::site(__($path, array(':file' => __($skeleton, array(
            ':width'    => $size['width'],
            ':height'   => $size['height'],
            ':name'     => $this->name(),
        )))));
    }

    /**
     * Sebenernya ini udah ga kapake lagi, soalnya udah dibuat thumbs di hook
     * 'image_after_save', tapi cuman buat mastiin aja
     */
    public function thumbnail_generator()
    {
        Hooks_File::create_thumbnail(Dispatcher::event(array(
            'file' => $this
        )));
    }

    /**
     * @param int $width
     * @param int $heigh
     * @return string
     */
    public function thumbnail_with_size($new_width, $new_height)
    {
        $image  = Image::factory($this->real_path());
        /* @var $image Image */

        $width  = $image->width;
        $height = $image->height;
        $y      = $width;

        if ($width < $height)
        {
            $image->crop($width, $width, 0, 0);
            $y = $width;
        }
        elseif ($width > $height)
        {
            $image->crop($height, $height, 0, 0);
            $y = $height;
        }

        $_config  = $this->upload_config();
        $path     = $_config['upload']['path'];
        $skeleton = $_config['thumbnails']['name'];
        $url      = $_config['upload']['url'];

        /* @var $i Image */
        $name   = __(':path/'.$skeleton, array(
            ':width'    => $new_width,
            ':height'   => $new_height,
            ':name'     => $this->name(),
            ':path'     => $path,
        ));

        if (! file_exists($name))
        {
            $master = Image::NONE;

            if ($new_width > $new_height)
            {
                $x = ($new_height * $y)/$new_width;
                $image->crop($y, $x)->save($name);
            }
            elseif ($new_width < $new_height)
            {
                $x = ($new_width * $y)/$new_height;
                $image->crop($x, $y)->save($name);
            }
            else
            {
                $master = NULL;
            }

            if ($master !== NULL)
            {
                $image = Image::factory($name);
            }

            $image->resize($new_width, $new_height, $master)
              ->save($name);
        }

        return URL::site(__($url, array(':file' => __($skeleton, array(
            ':width'    => $new_width,
            ':height'   => $new_height,
            ':name'     => $this->name(),
        )))));
    }

    public function __call($method, $args = array())
    {
        $method = trim(strtolower($method));

        if (preg_match('!^gallery_(?<action>index|delete)_url(?<uri_only>_only)?!', $method, $matches) && $this->_content)
        {
            $route      = 'admin-image-gallery';
            $action     = $matches['action'];

            if ($action == 'delete')
            {
                $action = 'delete_from_gallery';
            }

            $title      = isset($args[0]) ? $args[0] : NULL;
            $params     = isset($args[1]) ? $args[1] : array();
            $attrs      = isset($args[2]) ? $args[2] : NULL;
            $query      = isset($args[3]) ? $args[3] : array();

            if (isset($matches['uri_only']))
            {
                $params += array('uri_only' => TRUE);
            }

            return $this->_url($route, $action, $title, $params, $attrs, $query);
        }

        return parent::__call($method, $args);
    }

    protected function prepare_menu()
    {
        $menu = array(
            array(
                'title' => __(ORM::capitalize_title($this->_content->object_name())),
                'url'   => $this->_content->admin_update_url_only(),
            ),
            array(
                'title' => __('Images'),
                'url'   => $this->gallery_index_url_only(),
            ),
        );

        $this->_admin_menu = $menu;
    }

    public function render(array $attribues = NULL, $link_only = FALSE)
    {
        if (TRUE === $link_only)
            return $this->filelink;

        return HTML::image(ltrim($this->filelink, '/'), $attribues);
    }
}