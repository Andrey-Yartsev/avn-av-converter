<?php

namespace Converter\components;

use Converter\vendor\UploadHandler;

final class FileUploadHandler extends UploadHandler
{

    public function __construct($options = null, $initialize = true, $error_messages = null)
    {
        // call with $initialize = false to initialize only here
        if (empty($error_messages)) {
            $error_messages = [];
        }
        $error_messages['image_corrupted'] = 'Image is corrupted';

        parent::__construct($options, false, $error_messages);
        $this->options['accept_file_types'] = '/.+$/i';
        // Defines which files are handled as image files:
        $this->options['image_file_types'] = '/\.(gif|jpe?g|png)$/i';
        // Defines which files are handled as video files:
        $this->options['video_file_types'] = '/\.(mp4|moo?v|m4v|mpe?g|wmv|avi)$/i';
        if ($initialize) {
            $this->initialize();
        }
    }

    protected function get_upload_data($id) {
        return $_FILES[$id] ?? null;
    }

    protected function get_post_param($id) {
        return $_POST[$id] ?? null;
    }

    protected function get_query_param($id) {
        return $_GET[$id] ?? null;
    }

    protected function get_server_var($id) {
        return $_SERVER[$id] ?? null;
    }

    protected function create_scaled_image_from_video($file_name, $version, $options)
    {
        $file_path        = $this->get_upload_path($file_name);
        $preview_filename = $file_path . '.jpg';
        $command          = "ffmpeg -ss 00:00:01 -i " . escapeshellarg($file_path) . " -vframes 1 " . escapeshellarg($preview_filename);
        exec($command);
        if (file_exists($preview_filename)) {
            return $this->create_scaled_image(basename($preview_filename), $version, $options);
        }
    }

    protected function is_valid_video_file($file_path)
    {
        if (!preg_match($this->options['video_file_types'], $file_path)) {
            return false;
        }
        return true;
    }

    protected function handle_video_file($file_path, $file)
    {
        $failed_versions = [];
        foreach ($this->options['image_versions'] as $version => $options) {
            if ($this->create_scaled_image_from_video($file->name, $version, $options)) {
                if (!empty($version)) {
                    $file->{$version . 'Url'} = $this->get_download_url($file->name . '.jpg', $version);
                } else {
                    $file->size = $this->get_file_size($file_path, true);
                }
            } else {
                $failed_versions[] = $version ? $version : 'original';
            }
        }
        if (count($failed_versions)) {
            $file->error = $this->get_error_message('image_resize')
                . ' (' . implode($failed_versions, ', ') . ')';
        }
    }

    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null)
    {
        $file       = new \stdClass();
        $file->name = $this->get_file_name($uploaded_file, $name, $size, $type, $error, $index, $content_range);
        $file->size = $this->fix_integer_overflow((int)$size);
        $file->type = $type;
        if ($this->validate($uploaded_file, $file, $error, $index)) {
            $this->handle_form_data($file, $index);
            $upload_dir = $this->get_upload_path();
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, $this->options['mkdir_mode'], true);
            }
            $file_path   = $this->get_upload_path($file->name);
            $append_file = $content_range && is_file($file_path) && $file->size > $this->get_file_size($file_path);
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path, fopen($uploaded_file, 'r'), FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path, fopen('php://input', 'r'), $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = $this->get_file_size($file_path, $append_file);
            if ($file_size === $file->size) {
                $file->url  = $this->get_download_url($file->name);
                if ($this->is_valid_image_file($file_path)) {
                    if ($this->check_image_is_corrupted($file_path)) {
                        unset($file->url);
                        @unlink($file_path);
                        $file->error = $this->get_error_message('image_corrupted');
                        return $file;
                    }
                    $this->handle_image_file($file_path, $file);
                } else if ($this->is_valid_video_file($file_path)) {
                    $this->handle_video_file($file_path, $file);
                }
            } else {
                $file->size = $file_size;
                if (!$content_range && $this->options['discard_aborted_uploads']) {
                    unlink($file_path);
                    $file->error = $this->get_error_message('abort');
                }
            }
            $this->set_additional_file_properties($file);
        }
        return $file;
    }

    protected function check_image_is_corrupted($file_path)
    {
        if ($this->options['image_library'] === 1 && extension_loaded('imagick')) {
            try {
                /** @var \Imagick $image */
                $image = $this->imagick_get_image_object($file_path);
                $image->identifyImage();
            } catch (\Exception $e) {
                return true;
            }
        }

        return false;
    }

    protected function trim_file_name($file_path, $name, $size, $type, $error,
        $index, $content_range) {
        return strip_tags(parent::trim_file_name($file_path, $name, $size, $type, $error,
            $index, $content_range));
    }

}
