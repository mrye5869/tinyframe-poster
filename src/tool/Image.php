<?php
// +----------------------------------------------------------------------
// | zibi [ WE CAN DO IT MORE SIMPLE]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2020 http://xmzibi.com/ All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: MrYe    <email：55585190@qq.com>
// +----------------------------------------------------------------------

namespace og\haibao\tool;

class Image
{

    /**
     *
     * @param $filename
     * @return bool|resource
     */
    protected function ImageCreateFromBMP($filename)
    {
        if (!$f1 = fopen($filename, "rb"))
            return false;

        $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1, 14));
        if ($FILE['file_type'] != 19778)
            return false;

        $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel' .
            '/Vcompression/Vsize_bitmap/Vhoriz_resolution' .
            '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));
        $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
        if ($BMP['size_bitmap'] == 0)
            $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
        $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
        $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
        $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] = 4 - (4 * $BMP['decal']);
        if ($BMP['decal'] == 4)
            $BMP['decal'] = 0;

        $PALETTE = array();
        if ($BMP['colors'] < 16777216) {
            $PALETTE = unpack('V' . $BMP['colors'], fread($f1, $BMP['colors'] * 4));
        }

        $IMG = fread($f1, $BMP['size_bitmap']);
        $VIDE = chr(0);

        $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
        $P = 0;
        $Y = $BMP['height'] - 1;
        while ($Y >= 0) {
            $X = 0;
            while ($X < $BMP['width']) {
                switch ($BMP['bits_per_pixel']) {
                    case 32:
                        $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                        break;
                    case 24:
                        $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                        break;
                    case 16:
                        $COLOR = unpack("n", substr($IMG, $P, 2));
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 8:
                        $COLOR = unpack("n", $VIDE . substr($IMG, $P, 1));
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 4:
                        $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                        if (($P * 2) % 2 == 0)
                            $COLOR[1] = ($COLOR[1] >> 4);
                        else
                            $COLOR[1] = ($COLOR[1] & 0x0F);
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 1:
                        $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                        if (($P * 8) % 8 == 0)
                            $COLOR[1] = $COLOR[1] >> 7;
                        elseif (($P * 8) % 8 == 1)
                            $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                        elseif (($P * 8) % 8 == 2)
                            $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                        elseif (($P * 8) % 8 == 3)
                            $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                        elseif (($P * 8) % 8 == 4)
                            $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                        elseif (($P * 8) % 8 == 5)
                            $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                        elseif (($P * 8) % 8 == 6)
                            $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                        elseif (($P * 8) % 8 == 7)
                            $COLOR[1] = ($COLOR[1] & 0x1);
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    default:
                        return false;
                        break;
                }

                imagesetpixel($res, $X, $Y, $COLOR[1]);
                $X++;
                $P += $BMP['bytes_per_pixel'];
            }
            $Y--;
            $P+=$BMP['decal'];
        }
        fclose($f1);

        return $res;
    }
}