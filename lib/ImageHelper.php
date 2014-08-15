<?php

namespace Gallery;

class ImageHelper {
  
  static function resize($destination_folder, $source_folder, $image_destination_name, $image_media_name, $max_larg, $max_haut) {
  	if (!is_dir($destination_folder)) { mkdir($destination_folder, 0777);}
  	preg_match("'^(.*)\.(gif|jpe?g|png)$'i", $image_media_name, $ext);
  	switch (strtolower($ext[2])) {
  		case 'jpg' : 
  		case 'jpeg': $im  = imagecreatefromjpeg ($source_folder.$image_media_name);	break;
  		case 'JPG' : $im  = imagecreatefromjpeg ($source_folder.$image_media_name);	break;
  		case 'gif' : $im  = imagecreatefromgif  ($source_folder.$image_media_name);	break;
  		case 'png' : $im  = imagecreatefrompng  ($source_folder.$image_media_name);	break;
  		default    : $im  = imagecreatefromjpeg ($source_folder.$image_media_name);	break;
  	}
  	$image_media_name = $ext[1].'.'.strtolower($ext[2]);
  	$x = imagesx($im);
  	$y = imagesy($im);

  	if (($x>$max_larg) || ($y>$max_haut)){
  		$save = (($max_larg/$max_haut) < ($x/$y) ? 
  				imagecreatetruecolor($x/($x/$max_larg), $y/($x/$max_larg)) : 
  				imagecreatetruecolor($x/($y/$max_haut), $y/($y/$max_haut))
  		);

  		imagecopyresampled($save, $im, 0, 0, 0, 0, imagesx($save), imagesy($save), $x, $y);
  		switch (strtolower($ext[2])) {
  			case 'jpg' : imagejpeg($save, $destination_folder.$image_destination_name);	break;
  			case 'jpeg': imagejpeg($save, $destination_folder.$image_destination_name);	break;
  			case 'JPG' : imagejpeg($save, $destination_folder.$image_destination_name);	break;
  			case 'gif' : imagegif($save, $destination_folder.$image_destination_name);	break;
  			default    : imagejpeg($save, $destination_folder.$image_destination_name);	break;
  		}
  		imagedestroy($im);
  		imagedestroy($save);
  		}else{
  			copy($source_folder.$image_media_name, $destination_folder.$image_destination_name);		
  		}
  	return $image_destination_name;
  }
  
}