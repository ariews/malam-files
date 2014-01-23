<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author arie
 */

class Malam_Model_File extends ORM
{
    /**
     * Auto-update columns for creation
     * @var string
     */
    protected $_created_column  = array(
        'column'    => 'created_at',
        'format'    => 'Y-m-d H:i:s'
    );

    /**
     * Name Field
     *
     * @var string
     */
    protected $name_field       = 'file';

    /**
     * Config
     * @var Config
     */
    protected $_config;

    /**
     * Table name
     *
     * @var string
     */
    protected $_table_name      = 'files';

    /**
     * Content
     *
     * @var Model_Bigcontent
     */
    protected $_content;

    /**
     * "Belongs to" relationships
     *
     * @var array
     */
    protected $_belongs_to      = array(
        'user'          => array('model' => 'user', 'foreign_key' => 'user_id'),
    );

    /**
     * @var bool
     */
    protected $_is_direct_call  = TRUE;

    /**
     * "Has many" relationships
     *
     * @var array
     */
    protected $_has_many        = array(
        'contents'      => array(
            'model'         => 'bigcontent',
            'through'       => 'relationship_files',
            'foreign_key'   => 'file_id',
            'far_key'       => 'object_id',
        ),
    );

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        $this->_config = Kohana::$config->load('fileupload');
    }

    public function rules()
    {
        return array(
            'file'  => array(
                array('not_empty'),
            ),
            'type' => array(
                array('not_empty'),
            ),
        );
    }

    public function filters()
    {
        return array(
            'file' => array(
                array(array($this, 'Filter_File'))
            ),
            'user_id' => array(
                array('ORM::Check_Model', array(':value', 'user'))
            ),
        );
    }

    public function Filter_File($value)
    {
        if (is_array($value))
        {
            $config = $this->_config['upload'];
            $md5sum = md5_file($value['tmp_name']);

            try {
                if ($path = Upload::save($value, NULL, $config['path']))
                {
                    $filename = basename($path);
                    $filelink = URL::site(__($config['url'], array(':file' => $filename)));
                    $this->values(array(
                        'md5sum'    => $md5sum,
                        'filelink'  => $filelink
                    ));

                    $value = $filename;
                }
            }
            catch (ORM_Validation_Exception $e)
            {}
        }

        return $value;
    }

    /**
     * Insert a new object to the database
     *
     * @param  Validation $validation Validation object
     * @return ORM
     */
    public function create(Validation $validation = NULL)
    {
        $this->type = $this->object_name();
        return parent::create($validation);
    }

    /**
     * Initializes the Database Builder to given query type
     *
     * @param  integer $type Type of Database query
     * @return ORM
     */
    protected function _build($type)
    {
        if (! $this->is_direct_call())
        {
            $this->where('type', '=', $this->object_name());
        }

        if ($this->is_hidden())
        {
            $this->where('is_hidden', '=', FALSE);
        }

        return parent::_build($type);
    }

    public function __call($method, $args = array())
    {
        if ($this->is_direct_call() &&
            preg_match('/^(?<admin_action>(admin[_-])?(?<action>[^_]+))_url(?<uri_only>_only)?$/i', $method)
        )
        {
            $cnt = ORM::factory($this->type, $this->pk());
            return call_user_func_array(array($cnt, $method), $args);
        }

        return parent::__call($method, $args);
    }

    protected function is_direct_call()
    {
        return $this->_is_direct_call;
    }

    public function object_name()
    {
        if (! $this->is_direct_call())
        {
            return parent::object_name();
        }
        else
        {
            return $this->type;
        }
    }

    public function file_accept()
    {
        $config = $this->_config['upload']['accept'];
        return array_merge($config['image'], $config['audio'], $config['video']);
    }

    public static function Validation_Extra(array $values)
    {
        $config = $this->_config['upload'];

        return Validation::factory($values)
            ->rule('file', 'Upload::valid')
            ->rule('file', 'Upload::not_empty')
            ->rule('file', 'Upload::type', array(':value', $this->file_accept()))
            ->rule('file', 'Upload::size', array(':value', $config['max']))
            ;
    }

    public function upload_config()
    {
        return $this->_config;
    }

    /**
     *
     * @param type $values
     * @return self
     */
    public function save_from_post($values)
    {
        $return = FALSE;
        $file   = $values['file'];
        $md5sum = md5_file($file['tmp_name']);
        $check  = $this->find_by_md5sum($md5sum);

        if (FALSE !== $check && $check->loaded())
        {
            $return = $check;
        }
        else
        {
            $return = $this->values($values)->save();
            Dispatcher::instance()
                    ->trigger_event('file_after_save', Dispatcher::event(
                            array('file' => $return)));
        }

        return $return;
    }

    public function as_ajax_result()
    {
        $res = array(
            'filename'  => $this->file,
            'filelink'  => $this->filelink,
            'files'     => array(
                'name'  => $this->name(),
                'size'  => filesize($this->real_path()),
                'type'  => File::mime($this->real_path()),
                'url'   => $this->filelink,
                'md5sum'=> $this->md5sum
            ),
        );

        return $res;
    }

    public function real_path()
    {
        return $this->_config['upload']['path'].'/'.$this->file;
    }

    public function find_by_md5sum($md5sum)
    {
        $file = ORM::factory($this->object_name())
                ->where('md5sum', '=', $md5sum)
                ->find();

        return $file->loaded() ? $file : FALSE;
    }

    public function delete()
    {
        Dispatcher::instance()
                ->trigger_event('file_before_delete', Dispatcher::event(
                        array('file' => $this)));

        return parent::delete();
    }

    public function do_truncate()
    {
        $files = ORM::factory($this->object_name())->find_all();

        if ($files->count())
        {
            foreach ($files as $file)
            {
                try { $file->delete(); } catch (Exception $e) {}
            }
        }
    }

    public function set_content(Model_Bigcontent $content)
    {
        $this->_content = $content;
        return $this;
    }

    protected function link($action = 'index', $title = NULL, array $params = NULL, array $attributes = NULL, array $query = NULL)
    {
        empty($params) && $params = array();

        if ($this->_content)
        {
            $params += array(
                'model'     => $this->_content->object_name(),
                'model_id'  => $this->_content->pk()
            );
        }

        return parent::link($action, $title, $params, $attributes, $query);
    }

    public function to_paginate()
    {
        return Paginate::factory($this)
            ->sort('created_at', Paginate::SORT_DESC)
            ->columns(array($this->primary_key(), 'uploader', 'created at'));
    }

    public function get_field($field)
    {
        switch (strtolower($field)):
            case 'uploader':
                $user   = Auth::instance()->get_user();
                return $this->user->name();
                break;

            case 'created_at':
            case 'created at':
                return parent::get_field('created_at');
                break;

            default :
                return parent::get_field($field);
                break;
        endswitch;
    }
}