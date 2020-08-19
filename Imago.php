<?php

namespace Esyede;

class Imago
{
    /**
     * Stores single instace object.
     *
     * @var object
     */
    private static $instance;

    /**
     * Stores image resource.
     *
     * @var resource
     */
    protected $image;

    /**
     * Stores file path (absolute).
     *
     * @var string
     */
    protected $path;

    /**
     * Stores image quality.
     *
     * @var int
     */
    protected $quality;

    /**
     * Stores image width.
     *
     * @var int
     */
    protected $width;

    /**
     * Stores image height.
     *
     * @var int
     */
    protected $height;

    /**
     * Stores extracted exif data.
     *
     * @var array
     */
    protected $exif = [];

    /**
     * Constructor.
     *
     * @param string $path
     * @param int    $quality
     */
    public function __construct($path, $quality = 75)
    {
        $this->reset();

        $this->path = $this->path($path);

        if (! is_file($this->path)) {
            throw new \Exception('Source image does not exists: '.$this->path);
        }

        $quality = $this->level($quality, 0, 100, 'quality');

        $this->width = 0;
        $this->height = 0;
        $this->quality = $quality;

        $this->load($path);
    }

    /**
     * Open an image for processing
     * Supported formats: jpg, png, gif.
     *
     * @param string $path
     *
     * @return $this
     */
    public static function open($path, $quality = 75)
    {
        if (! is_null(self::$instance)) {
            static::$instance->reset();

            return static::$instance;
        }

        static::$instance = new static($path, $quality);

        return static::$instance;
    }

    /**
     * Load an image file.
     *
     * @param string $path
     *
     * @return $this
     */
    protected function load($path)
    {
        if (! static::available()) {
            throw new \Exception('The PHP GD extension is not available.');
        }

        if (! $this->acceptable($path)) {
            throw new \Exception(
                'Invalid image type: '.$mime.'. '.
                'Only jpg, png and gif is supported.'
            );
        }

        list($this->width, $this->height, $this->type) = getimagesize($path);

        if ($this->type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $this->exif = exif_read_data($path, 'IFD0');
        } else {
            $this->exif = [];
        }

        switch ($this->type) {
            case IMAGETYPE_JPEG: $this->image = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $this->image = imagecreatefrompng($path);  break;
            case IMAGETYPE_GIF:  $this->image = imagecreatefromgif($path);  break;
            default:             throw new \Exception('Attempted to load a non-supported image');
        }

        return $this;
    }

    /**
     * Resize image width.
     *
     * @param int $value
     *
     * @return $this
     */
    public function width($value)
    {
        $width = (int) $value;
        $newHeight = ($value / $this->width) * $this->height;
        $canvas = imagecreatetruecolor($value, $newHeight);

        imagecopyresampled(
            $canvas,
            $this->image,
            0,
            0,
            0,
            0,
            $value,
            $newHeight,
            $this->width,
            $this->height
        );

        $this->image = $canvas;
        $this->maintain();

        return $this;
    }

    /**
     * Resize image height.
     *
     * @param int $value
     *
     * @return $this
     */
    public function height($value)
    {
        $value = (int) $value;
        $newWidth = ($value / $this->height) * $this->width;
        $canvas = imagecreatetruecolor($newWidth, $value);

        imagecopyresampled(
            $canvas,
            $this->image,
            0,
            0,
            0,
            0,
            $newWidth,
            $value,
            $this->width,
            $this->height
        );

        $this->image = $canvas;
        $this->maintain();

        return $this;
    }

    /**
     * Rotate image per 90 degrees angle.
     *
     * @param int $angle
     *
     * @return $this
     */
    public function rotate($angle = 90)
    {
        $angle = (int) $angle;

        if ($angle % 90 > 0) {
            throw new \Exception('The image can only be rotated at 90 degree intervals.');
        }

        $this->image = imagerotate($this->image, $angle, 0);
        $this->maintain();

        return $this;
    }

    /**
     * Crop current image.
     *
     * @param int $left
     * @param int $top
     * @param int $width
     * @param int $height
     *
     * @return $this
     */
    public function crop($left, $top, $width, $height)
    {
        if (($left + $width) > $this->width || ($top + $height) > $this->height) {
            throw new \Exception('The cropping selection is out of bounds.');
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagecopy(
            $canvas,
            $this->image,
            0,
            0, // destination
            $left,
            $top, // source
            $width,
            $height
        );

        $this->image = $canvas;
        $this->maintain();

        return $this;
    }

    /**
     * Resize current image from the center using ratios.
     * e.g. a 500x200 at a 1:1 (a square) size will result in a 200x200 image.
     * e.g. a 500x200 at a 3:4 size will result in a 150x200 image.
     *
     * @param int $width
     * @param int $height
     *
     * @return $this
     */
    public function ratio($width = 1, $height = 1)
    {
        if ($width < 0) {
            throw new \Exception('The width ratio must be a greater than zero.');
        }

        if ($height < 0) {
            throw new \Exception('The height ratio must be a greater than zero.');
        }

        // original and new ratios
        $original = $this->width / $this->height;
        $new = $width / $height;

        // no need to do any processing if ratios are the same!
        if ($new === $original) {
            return $this;
        }

        // if the new ratio has a greater height
        if ($new < $original) {
            $newWidth = ($this->height / $height) * $width;
            $newHeight = $this->height;
            $x = ($this->width / 2) - $newWidth / 2;
            $y = 0;
        }

        // if the new ratio has a greater width
        if ($new > $original) {
            $newHeight = ($this->width / $width) * $height;
            $newWidth = $this->width;
            $x = 0;
            $y = ($this->height / 2) - $newHeight / 2;
        }

        // crop image from center
        $this->crop($x, $y, $newWidth, $newHeight);

        return $this;
    }

    /**
     * Apply contrast filter (range: -100 to +100).
     *
     * @param int $level
     *
     * @return $this
     */
    public function contrast($level)
    {
        $level = $this->level($level, -100, 100, 'contrast');
        imagefilter($this->image, IMG_FILTER_CONTRAST, $level);

        return $this;
    }

    /**
     * Apply brightness filter (range: -100 to +100).
     *
     * @param int $level
     *
     * @return $this
     */
    public function brightness($level)
    {
        $level = $this->level($level, -100, 100, 'brightness');
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level);

        return $this;
    }

    /**
     * Apply smoothness filter (range: -100 to +100).
     *
     * @param int $level
     *
     * @return $this
     */
    public function smoothness($level)
    {
        $level = $this->level($level, -100, 100, 'smoothness');
        imagefilter($this->image, IMG_FILTER_SMOOTH, $level);

        return $this;
    }

    /**
     * Apply blur filter with option to select
     * between gaussian / selective blur.
     *
     * @param bool $selective
     *
     * @return $this
     */
    public function blur($selective = false)
    {
        $selective = $selective ? IMG_FILTER_SELECTIVE_BLUR : IMG_FILTER_GAUSSIAN_BLUR;
        imagefilter($this->image, $selective);

        return $this;
    }

    /**
     * Apply grayscale filter.
     *
     * @return $this
     */
    public function grayscale()
    {
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);

        return $this;
    }

    /**
     * Apply sepia filter.
     *
     * @return $this
     */
    public function sepia()
    {
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        imagefilter($this->image, IMG_FILTER_COLORIZE, 90, 60, 45);

        return $this;
    }

    /**
     * Apply the edges-highlight filter.
     *
     * @return $this
     */
    public function edge()
    {
        imagefilter($this->image, IMG_FILTER_EDGEDETECT);

        return $this;
    }

    /**
     * Apply emboss filter.
     *
     * @return $this
     */
    public function emboss()
    {
        imagefilter($this->image, IMG_FILTER_EMBOSS);

        return $this;
    }

    /**
     * Apply sketch filter.
     *
     * @return $this
     */
    public function sketch()
    {
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);

        return $this;
    }

    /**
     * Apply invert color filter.
     *
     * @return $this
     */
    public function invert()
    {
        imagefilter($this->image, IMG_FILTER_NEGATE);

        return $this;
    }

    /**
     * Apply pixelate filter (range: -100 to +100)/.
     *
     * @param int $value
     *
     * @return $this
     */
    public function pixelate($value)
    {
        $level = $this->level($level, -100, 100, 'pixelate');
        imagefilter($this->image, IMG_FILTER_PIXELATE, $value, $end);

        return $this;
    }

    /**
     * Save image file to disk.
     *
     * @param string $path
     * @param bool   $overwrite
     *
     * @return bool
     */
    public function export($path, $overwrite = false)
    {
        $this->maintain();
        $this->path = $this->path($path);

        if (is_file($this->path)) {
            if (! $overwrite) {
                throw new \Exception('Destination file already exists: '.$this->path);
            }
        }

        if (false === strpos($this->path, '.')) {
            if (! $overwrite) {
                throw new \Exception('Unsupported file: '.$this->path);
            }
        }

        $extension = explode('.', $this->path);
        $extension = strtolower(end($extension));

        switch ($extension) {
            case 'jpg':
                if (! imagejpeg($this->image, $this->path, $this->quality)) {
                    throw new \Exception('The jpg file could not be saved!');
                }
                break;
            case 'png':
                imagealphablending($this->image, false);
                imagesavealpha($this->image, true);
                if (! imagepng($this->image, $this->path)) {
                    throw new \Exception('The png file could not be saved.');
                }
                break;
            case 'gif':
                if (! imagegif($this->image, $this->path, $this->quality)) {
                    throw new \Exception('The gif file could not be saved.');
                }
                break;
            default:
                throw new \Exception(
                    'Bad filetype given, must be jpg, png or gif.'
                );
        }
    }

    /**
     * Dump image resource.
     *
     * @return resource
     */
    public function dump()
    {
        $result = imagepng($this->image);
        $this->reset();

        return $result;
    }

    /**
     * Preview image in the web browser.
     *
     * @return Response
     */
    public function preview()
    {
        header('Content-Type: image/jpeg');
        echo $this->dump();
        exit;
    }

    /**
     * Returns image informations.
     *
     * @return array
     */
    public function info()
    {
        $type = null;

        switch ($this->type) {
            case IMAGETYPE_JPEG: $type = 'image/jpeg'; break;
            case IMAGETYPE_PNG:  $type = 'image/png';  break;
            case IMAGETYPE_GIF:  $type = 'image/gif';  break;
        }

        return [
            'path' => $this->path,
            'width' => $this->width,
            'height' => $this->height,
            'quality' => $this->quality,
            'exif' => $this->exif,
        ];
    }

    /**
     * Reset image properties.
     *
     * @return void
     */
    public function reset()
    {
        if (is_resource($this->image)) {
            imagedestroy($this->image);
        }

        $this->image = null;
        $this->path = null;
        $this->width = 0;
        $this->height = 0;
        $this->quality = null;
        $this->exif = [];
    }

    /**
     * Make identicon image.
     *
     * @param string $seed
     * @param int    $size
     * @param bool   $display
     *
     * @return Response|resource
     */
    public static function identicon($seed, $size = 64, $display = false)
    {
        if (! static::available()) {
            throw new \Exception('The PHP GD extension is not available');
        }

        if ($size < 16) {
            $size = 16;
        }

        $hash = sha1($seed);
        $sprites = static::sprites();
        $image = imagecreatetruecolor($size, $size);
        list($r, $g, $b) = static::rgb(hexdec(substr($hash, -3)));

        $color = imagecolorallocate($image, $r, $g, $b);
        imagefill($image, 0, 0, IMG_COLOR_TRANSPARENT);

        $ctr = count($sprites);
        $dimension = 4 * floor($size / 4) * 0.5;

        for ($j = 0, $y = 2; $j < $y; $j++) {
            for ($i = $j, $x = 3 - $j; $i < $x; $i++) {
                $sprite = imagecreatetruecolor($dimension, $dimension);
                imagefill($sprite, 0, 0, IMG_COLOR_TRANSPARENT);
                $block = $sprites[hexdec($hash[($j * 4 + $i) * 2]) % $ctr];

                for ($k = 0, $points = count($block); $k < $points; $k++) {
                    $block[$k] *= $dimension;
                }

                imagefilledpolygon($sprite, $block, $points / 2, $color);

                for ($k = 0; $k < 4; $k++) {
                    imagecopyresampled(
                        $image,
                        $sprite,
                        $i * $dimension / 2,
                        $j * $dimension / 2,
                        0,
                        0,
                        $dimension / 2,
                        $dimension / 2,
                        $dimension,
                        $dimension
                    );

                    $image = imagerotate(
                        $image,
                        90,
                        imagecolorallocatealpha($image, 0, 0, 0, 127)
                    );
                }

                imagedestroy($sprite);
            }
        }

        imagesavealpha($image, true);
        $result = imagepng($image);
        imagedestroy($image);

        if ($display) {
            header('Content-Type: image/png');
            echo $result;
            exit;
        }

        return $result;
    }

    /**
     * Convert RGB hex triad to array.
     *
     * @param int|string $color
     *
     * @return array|false
     */
    public static function rgb($color)
    {
        if (is_string($color)) {
            $color = hexdec($color);
        }

        $hex = str_pad($hex = dechex($color), $color < 4096 ? 3 : 6, '0', STR_PAD_LEFT);

        if (($length = strlen($hex)) > 6) {
            throw new \Exception('Invalid color specified: 0x'.$hex);
        }

        $color = str_split($hex, $length / 3);

        foreach ($color as &$hue) {
            $hue = hexdec(str_repeat($hue, 6 / $length));
            unset($hue);
        }

        return $color;
    }

    /**
     * Sprite data for identicon.
     *
     * @return array
     */
    private static function sprites()
    {
        return [
            [.5, 1, 1, 0, 1, 1],
            [.5, 0, 1, 0, .5, 1, 0, 1],
            [.5, 0, 1, 0, 1, 1, .5, 1, 1, .5],
            [0, .5, .5, 0, 1, .5, .5, 1, .5, .5],
            [0, .5, 1, 0, 1, 1, 0, 1, 1, .5],
            [1, 0, 1, 1, .5, 1, 1, .5, .5, .5],
            [0, 0, 1, 0, 1, .5, 0, 0, .5, 1, 0, 1],
            [0, 0, .5, 0, 1, .5, .5, 1, 0, 1, .5, .5],
            [.5, 0, .5, .5, 1, .5, 1, 1, .5, 1, .5, .5, 0, .5],
            [0, 0, 1, 0, .5, .5, 1, .5, .5, 1, .5, .5, 0, 1],
            [0, .5, .5, 1, 1, .5, .5, 0, 1, 0, 1, 1, 0, 1],
            [.5, 0, 1, 0, 1, 1, .5, 1, 1, .75, .5, .5, 1, .25],
            [0, .5, .5, 0, .5, .5, 1, 0, 1, .5, .5, 1, .5, .5, 0, 1],
            [0, 0, 1, 0, 1, 1, 0, 1, 1, .5, .5, .25, .5, .75, 0, .5, .5, .25],
            [0, .5, .5, .5, .5, 0, 1, 0, .5, .5, 1, .5, .5, 1, .5, .5, 0, 1],
            [0, 0, 1, 0, .5, .5, .5, 0, 0, .5, 1, .5, .5, 1, .5, .5, 0, 1],
        ];
    }

    /**
     * Maintain image width and height attributes.
     */
    protected function maintain()
    {
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Resolve path to image (absolute).
     *
     * @param string $path
     *
     * @return string
     */
    public function path($path)
    {
        $path = ltrim(ltrim($path, '/'), '\\');
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return $path;
    }

    /**
     * Check PHP GD availability.
     *
     * @return bool
     */
    public static function available()
    {
        return extension_loaded('gd');
    }

    /**
     * Check if image format is acceptable.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function acceptable($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $acceptable = ['png', 'jpg', 'gif'];

        return in_array($extension, $acceptable);
    }

    /**
     * Handle level validation.
     *
     * @param int    $value
     * @param int    $low
     * @param int    $high
     * @param string $method
     *
     * @return int
     */
    private function level($value, $low, $high, $method)
    {
        $bounds = range($low, $high);

        if (! in_array($value, $bounds)) {
            throw new \Exception(
                'The '.$method.' level is out of bounds. '.
                'It needs to be between '.$low.' to '.$high
            );
        }

        return  (int) $value;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->reset();
    }
}
