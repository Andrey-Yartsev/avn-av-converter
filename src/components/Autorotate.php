<?php
/**
 * User: pel
 * Date: 2019-05-03
 */

namespace Converter\components;


class Autorotate extends \Imagine\Filter\Basic\Autorotate
{
    /**
     * Get the transformations.
     *
     * @param \Imagine\Image\ImageInterface $image
     *
     * @return array an array containing Autorotate::FLIP_VERTICALLY, Autorotate::FLIP_HORIZONTALLY, rotation degrees
     */
    public function getTransformations(ImageInterface $image)
    {
        $transformations = array();
        $metadata = $image->metadata();
        switch (isset($metadata['ifd0.Orientation']) ? $metadata['ifd0.Orientation'] : null) {
            case 1: // top-left
                break;
            case 2: // top-right
                $transformations[] = self::FLIP_HORIZONTALLY;
                break;
            case 3: // bottom-right
                $transformations[] = 180;
                break;
            case 4: // bottom-left
                $transformations[] = self::FLIP_HORIZONTALLY;
                $transformations[] = 180;
                break;
            case 5: // left-top
                $transformations[] = self::FLIP_HORIZONTALLY;
                $transformations[] = -90;
                break;
            case 6: // right-top
                $transformations[] = 0;
                break;
            case 7: // right-bottom
                $transformations[] = self::FLIP_HORIZONTALLY;
                $transformations[] = 90;
                break;
            case 8: // left-bottom
                $transformations[] = -90;
                break;
            default: // Invalid orientation
                break;
        }
        return $transformations;
    }
}