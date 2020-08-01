# Imago

Simple, standalone GD image manipulation library written in PHP.


### Requirements

  - PHP 5.4+
  - PHP GD extension


### Installation

Download the file from [release page](https://github.com/esyede/imago/releases) and drop to your project. That's it.


### Loading image

```php
require 'Imago.php';

use Esyede\Imago;

$imago = Imago::open('myimage.jpg')
```

You can also set the export quality while loading the image:

```php
$imago = Imago::open('test.jpg', 75);
```
> **Note:** Accepted range is between `0` to `100`.


### Resize

Resize image width:

```php
$imago->width(100); // 100 pixel
```

Resize image height:
```php
$imago->height(100); // 100 pixel
```


### Rotate
```php
$imago->rotate(90); // rotate 90 degree

$imago->rotate(180); // rotate 180 degree
```

> **Note:** This `rotate()` method will only accepts values multiplied by 90.


### Crop

```php
$left = 50;
$top = 20;
$width = 100;
$height = 100;

$imago->crop($left, $top, $width, $height);
```

You may also use ratio-based cropping:

```php
$width = 2;
$height = 1;

$imago->ratio($width, $height);
```


### Effects

Brightness:
```php
$imago->brightness(40);
```

Contrast:
```php
$imago->contrast(80);
```

Smoothness:
```php
$imago->smoothness(80);
```

Gaussian blur:
```php
$imago->blur();
```

Selective blur:
```php
$imago->blur(true);
```

Grayscale:
```php
$imago->grayscale(35);
```
> **Note:** Accepted range is between `-100` to `100`.


### Preview & Exporting

Preview current result via web browser:
```php
$imago->preview();
```

Exporting result into a file:
```php
$imago->export('cat.png');
```


### Additional Features

Read image info:
```php
$imago->info();
```

Make an identicon image:

```php
// Make an identicon with default size (64 pixel)
$identicon = Imago::identicon('john.doe@gmail.com');

// Make an identicon with custom size
$identicon = Imago::identicon('john.doe@gmail.com', 200);

// Preview identicon via web browser
return Imago::identicon('john.doe@gmail.com', 64, true);

// Export identicon into a file
file_put_contents('user.jpg', $identicon, LOCK_EX);
```

That's pretty much it. Thank you for stopping by!

### License
This library is licensed under the [MIT License](http://opensource.org/licenses/MIT)
