<?php

/**
 * album_thumbnail class
 * 
 * Generates a collage thumbnail from four images. The layout used will be based on the
 * aspect ratios of the images provided.  You should change the default values of the
 * parameters for the constructor if the same values will be used all the time.  
 * 
 * Example usage:
 *   $at = new album_thumbnail();
 *   $at->add_image('/images/001.jpg');
 *   $at->add_image('/images/002.jpg');
 *   $at->add_image('/images/003.jpg');
 *   $at->add_image('/images/004.jpg');
 *   
 *   $at->make_thumbnail('/images/thumb1234.jpg');
 * 
 * ============================================================================
 * 
 * LAYOUTS - where image 0 is the narrowest (lowest width:height ratio), image 3 is the widest.
 *  
 *   LAYOUT 4A - for mostly normal proportions
 *   +---+-------+
 *   | 1 |   2   |
 *   +---+-----+-+
 *   |    3    |0|
 *   +---------+-+
 *   
 *   LAYOUT 4B - for two tall and two normal photos.
 *   +---+-----+---+
 *   |   |  3  |   |
 *   | 1 +-----+ 0 |
 *   |   |  2  |   |
 *   +---+-----+---+
 *   
 *   LAYOUT 4C - for one image taller than the rest
 *   +---+-----------+
 *   | 1 |           |
 *   +---+           |
 *   | 3 |     0     |
 *   +---+           |
 *   | 2 |           |
 *   +---+-----------+
 *   
 *   LAYOUT 4D - for all wide photos
 *   +--------------+
 *   |      1       |
 *   +--------------|
 *   |      3       |
 *   +--------------|
 *   |      2       |
 *   +--------------+
 *   |      0       |
 *   +--------------+
 *   
 *   LAYOUT 3A - for three tall photos
 *   +---+-----+-+
 *   |   |     | |
 *   | 1 |  2  |0|
 *   |   |     | |
 *   +---+-----+-+
 * 
 * @author Kip Robinson, https://github.com/kiprobinson
 */
class album_thumbnail
{
  var $images = array();
  var $images_idx = array();
  var $ratios = array();
  var $width;
  var $padding;
  var $border_width;
  var $bg_color;
  var $border_color;
  
  /**
   * You probably want to change the default values of these parameters so that you don't have to
   * specify them everytime.
   * 
   * @param $width        Total width of the thumbnail in pixels, including padding and border.
   * @param $padding      Width in pixels of gap between photos, and gap between photo and edge.
   *                      Does not include borders.
   * @param $border_width Width in pixels of border around each photo. No border is drawn around
   *                      the whole image.
   * @param $bg_color     The color to use for the background (i.e. the padding).
   * @param $border_color The color to use for the borders around the photos.
   */     
  function __construct($width = 196, $padding = 2, $border_width = 1, $bg_color = 0xffffff, $border_color = 0x808080)
  {
    $this->width = $width;
    $this->padding = $padding;
    $this->border_width = $border_width;
    $this->bg_color = $bg_color;
    $this->border_color = $border_color;
  }
  
  function __destruct()
  {
    $this->cleanup();
  }
  
  function cleanup()
  {
    foreach($this->images as $image)
      imagedestroy($image);
    
    $this->images = array();
  }
  
  function add_image($file)
  {
    $src = false;
    $ext = strtolower(strrchr($file, '.'));
    if ($ext == '.jpg' || $ext == '.jpeg' || $ext == '.jpe')
      $src = imagecreatefromjpeg($file);
    else if ($ext == '.gif')
      $src = imagecreatefromgif($file);
    else if ($ext == '.png')
      $src = imagecreatefrompng($file);
    
    if ($src != false)
      $this->images[] = $src;
  }
  
  
  function make_thumbnail($dest_file)
  {
    if (count($this->images) < 4)
      return;
    $this->compute_ratios();
    
    $layouts = array();
    
    //Determine what layout to use
    if ($this->ratios[0] > 2.0) //all pretty wide
    {
      $layouts = $this->layout_4d();
    }
    else if ($this->ratios[2] < 0.8) //all images pretty tall
    {
      //drop the widest image, use vertical panel layout
      imagedestroy($this->images[3]);
      array_pop($this->images);
      $layouts = $this->layout_3a();
    }
    else if ($this->ratios[0] <= 1.0 && $this->ratios[1] > 1.2) //one tall image
    {
      $layouts = $this->layout_4c();
    }
    else if ($this->ratios[1] <= 1.0 && $this->ratios[2] > 1.3 && mt_rand(0,1))  //2 wide, 2 tall: 50% chance of 4b
    {
      $layouts = $this->layout_4b();
    }
    else  //all other combinations
    {
      $layouts = $this->layout_4a();
    }
    
    $this->build_thumb($layouts, $dest_file);
    
    $this->cleanup();
  }
  
  
  //Private methods
  private function compute_ratios()
  {
    //first sort images by their aspect ratios
    usort($this->images, create_function('&$a, &$b', '$r=imagesx($a)/imagesy($a)-imagesx($b)/imagesy($b);return($r<0?-1:($r>0?1:0));') );
    
    //clear $ratios (shouldn't be needed.. just in case)
    if (count($this->ratios) != 0)
      $this->ratios = array();
    
    //store ratios in $ratios array, parallel to $images array
    foreach($this->images as $image)
      $this->ratios[] = imagesx($image) / imagesy($image);
  }
  
  private function build_thumb($layouts, $file)
  {
    $height = 0;
    foreach ($layouts as $layout)
    {
      if ($layout['h'] + $layout['y'] > $height)
        $height = $layout['h'] + $layout['y'];
    }
    $height += $this->padding + $this->border_width;
    $canvas = imagecreatetruecolor($this->width, $height);
    imagefill($canvas, 0, 0, $this->bg_color);
    
    $border = imagecreatetruecolor(4, 4);
    imagefill($border, 0, 0, $this->border_color);
    
    for ($i = 0; $i < count($layouts); $i++)
    {
      imagecopyresized ( $canvas, $border, 
                         $layouts[$i]['x'] -   $this->border_width, $layouts[$i]['y'] -   $this->border_width, 0, 0,
                         $layouts[$i]['w'] + 2*$this->border_width, $layouts[$i]['h'] + 2*$this->border_width, 4, 4 );
      imagecopyresampled($canvas, $this->images[$i],
                         $layouts[$i]['x'], $layouts[$i]['y'], 0, 0,
                         $layouts[$i]['w'], $layouts[$i]['h'], imagesx($this->images[$i]), imagesy($this->images[$i]) );
      
    }
    if (file_exists($file))
      unlink($file);
    imagejpeg($canvas, $file, 75);
    
    imagedestroy($border);
    imagedestroy($canvas);
  }
  
  
  //-------------------------------------------------------------
  //  LAYOUT FUNCTIONS
  //-------------------------------------------------------------
  
  
  // Layout 4a - for mostly normal proportions
  // +---+-------+
  // | 1 |   2   |
  // +---+-----+-+
  // |    3    |0|
  // +---------+-+
  private function layout_4a()
  {
    $ret = array();
    
    //$h1 = height of row 1, $h2 = height of row 2
    $h1 = ($this->width - 3*$this->padding - 4*$this->border_width) /
          ($this->ratios[1] + $this->ratios[2]);
    $h2 = ($this->width - 3*$this->padding - 4*$this->border_width) /
          ($this->ratios[3] + $this->ratios[0]);
    
    $ret[1] = array(
                    'w' => round( $this->ratios[1] * $h1 ),
                    'h' => round( $h1 ),
                    'x' => $this->padding + $this->border_width,
                    'y' => $this->padding + $this->border_width
                   );
    
    $ret[2] = array(
                    'w' => $this->width - $ret[1]['w'] - 3*$this->padding - 4*$this->border_width,
                    'h' => $ret[1]['h'],
                    'x' => $ret[1]['x'] + $ret[1]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    $ret[3] = array(
                    'w' => round( $this->ratios[3] * $h2 ),
                    'h' => round( $h2 ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[1]['y'] + $ret[1]['h'] + $this->padding + 2*$this->border_width
                   );
    
    $ret[0] = array(
                    'w' => $this->width - $ret[3]['w'] - 3*$this->padding - 4*$this->border_width,
                    'h' => $ret[3]['h'],
                    'x' => $ret[3]['x'] + $ret[3]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[3]['y']
                   );
    
    return $ret;
  }
  
  // LAYOUT 4B - for two tall and two normal photos.
  // +---+-----+---+
  // |   |  3  |   |
  // | 1 +-----+ 0 |
  // |   |  2  |   |
  // +---+-----+---+
  private function layout_4b()
  {
    $ret = array();
    
    //$h1 = height of image 1, etc
    $h1 = ($this->width - 2*$this->padding - 2*$this->border_width +
           ($this->padding + 2*$this->border_width)*
           (1/(1/$this->ratios[3] + 1/$this->ratios[2]) - 2)) /
          ($this->ratios[1] + $this->ratios[0] +
           1/(1/$this->ratios[3] + 1/$this->ratios[2]));
    $h3 = ($h1 - $this->padding - 2*$this->border_width) /
          (1 + $this->ratios[3] / $this->ratios[2]);
    $h2 = $h1 - $h3 - $this->padding - 2*$this->border_width;
    
    $ret[1] = array(
                    'w' => round( $this->ratios[1] * $h1 ),
                    'h' => round( $h1 ),
                    'x' => $this->padding + $this->border_width,
                    'y' => $this->padding + $this->border_width
                   );
    
    $ret[3] = array(
                    'w' => round( $this->ratios[3] * $h3 ),
                    'h' => round( $h3 ),
                    'x' => $ret[1]['x'] + $ret[1]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    $ret[2] = array(
                    'w' => $ret[3]['w'],
                    'h' => $ret[1]['h'] - $ret[3]['h'] - $this->padding - 2*$this->border_width,
                    'x' => $ret[3]['x'],
                    'y' => $ret[3]['y'] + $ret[3]['h'] + $this->padding + 2*$this->border_width
                   );
    
    $ret[0] = array(
                    'w' => $this->width - $ret[1]['w'] - $ret[3]['w'] - 4*$this->padding - 6*$this->border_width,
                    'h' => $ret[1]['h'],
                    'x' => $ret[2]['x'] + $ret[2]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    return $ret;
  }
  
  // LAYOUT 4C - for one image taller than the rest
  // +---+-----------+
  // | 1 |           |
  // +---+           |
  // | 3 |     0     |
  // +---+           |
  // | 2 |           |
  // +---+-----------+
  private function layout_4c()
  {
    $ret = array();
    
    $h0 = ($this->width - 2*$this->padding - 2*$this->border_width -
           ($this->padding + 2*$this->border_width) *
           (1 - (2/(1/$this->ratios[3] + 1/$this->ratios[2] + 1/$this->ratios[1])))) / 
          ($this->ratios[0] + (1/(1/$this->ratios[3] + 1/$this->ratios[2] + 1/$this->ratios[1])));
    $h1 = ($h0 - 2*$this->padding - 4*$this->border_width) /
          ($this->ratios[1]*(1/$this->ratios[3] + 1/$this->ratios[2]) + 1);
    $h3 = $h1 * $this->ratios[1] / $this->ratios[3];
    $h2 = $h0 - $h1 - $h3 - 2*$this->padding - 4*$this->border_width;
    
    $ret[1] = array(
                    'w' => round( $this->ratios[1] * $h1 ),
                    'h' => round( $h1 ),
                    'x' => $this->padding + $this->border_width,
                    'y' => $this->padding + $this->border_width
                   );
    
    $ret[3] = array(
                    'w' => $ret[1]['w'],
                    'h' => round( $h3 ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[1]['y'] + $ret[1]['h'] + $this->padding + 2*$this->border_width,
                   );
    
    $ret[2] = array(
                    'w' => $ret[1]['w'],
                    'h' => round( $h2 ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[3]['y'] + $ret[3]['h'] + $this->padding + 2*$this->border_width,
                   );
    
    $ret[0] = array(
                    'w' => $this->width - $ret[1]['w'] - 3*$this->padding - 4*$this->border_width,
                    'h' => $ret[1]['h'] + $ret[3]['h'] + $ret[2]['h'] + 2*$this->padding + 4*$this->border_width,
                    'x' => $ret[1]['x'] + $ret[1]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    return $ret;
  }
  
  // LAYOUT 4D - for all wide photos
  // +--------------+
  // |      1       |
  // +--------------|
  // |      3       |
  // +--------------|
  // |      2       |
  // +--------------+
  // |      0       |
  // +--------------+
  private function layout_4d()
  {
    $ret = array();
    
    $w1 = $this->width - 2*$this->padding - 2*$this->border_width;
    
    $ret[1] = array(
                    'w' => $w1,
                    'h' => round( $w1 / $this->ratios[1] ),
                    'x' => $this->padding + $this->border_width,
                    'y' => $this->padding + $this->border_width,
                   );
    
    $ret[3] = array(
                    'w' => $ret[1]['w'],
                    'h' => round( $w1 / $this->ratios[3] ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[1]['y'] + $ret[1]['h'] + $this->padding + 2*$this->border_width
                   );
    
    $ret[2] = array(
                    'w' => $ret[1]['w'],
                    'h' => round( $w1 / $this->ratios[2] ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[3]['y'] + $ret[3]['h'] + $this->padding + 2*$this->border_width
                   );
    
    $ret[0] = array(
                    'w' => $ret[1]['w'],
                    'h' => round( $w1 / $this->ratios[0] ),
                    'x' => $ret[1]['x'],
                    'y' => $ret[2]['y'] + $ret[2]['h'] + $this->padding + 2*$this->border_width
                   );
    
    return $ret;
  }
  
  // LAYOUT 3A - for three tall photos
  // +---+-----+-+
  // |   |     | |
  // | 1 |  2  |0|
  // |   |     | |
  // +---+-----+-+
  private function layout_3a()
  {
    $ret = array();
    
    $h1 = ($this->width - 4*$this->padding - 6*$this->border_width) /
          ($this->ratios[0] + $this->ratios[1] + $this->ratios[2]);
    
    $ret[1] = array(
                    'w' => round( $this->ratios[1] * $h1 ),
                    'h' => round( $h1 ),
                    'x' => $this->padding + $this->border_width,
                    'y' => $this->padding + $this->border_width
                   );
    
    $ret[2] = array(
                    'w' => round( $this->ratios[2] * $h1 ),
                    'h' => $ret[1]['h'],
                    'x' => $ret[1]['x'] + $ret[1]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    $ret[0] = array(
                    'w' => $this->width - $ret[1]['w'] - $ret[2]['w'] - 4*$this->padding - 6*$this->border_width,
                    'h' => $ret[1]['h'],
                    'x' => $ret[2]['x'] + $ret[2]['w'] + $this->padding + 2*$this->border_width,
                    'y' => $ret[1]['y']
                   );
    
    return $ret;
  }
}

