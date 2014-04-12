<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_numbers extends WYSIJA_object{
    
    function WYSIJA_help_numbers(){
        
    }
    
    
    function format_number($int) {
        // strip any commas 
        $int = (int)(0 + str_replace(',', '', $int));

        // make sure it's a number...
        if(!is_numeric($int)){ return false;}

        // filter and format it 
        if($int>1000000000000){ 
                    return round(($int/1000000000000),2).' trillion';
        }elseif($int>1000000000){ 
                    return round(($int/1000000000),2).' billion';
        }elseif($int>1000000){ 
                    return round(($int/1000000),2).' million';
        }elseif($int>1000){ 
            return round(($int/1000),2).' thousand';
        }

        return number_format($int);
    }
    
    /**
     * Calculate percetage of $a, based $b; and round down
     * @param mixed $a
     * @param mixed $b
     * @param type $decimal_number
     * @return real 
     */
    function calculate_percetage($a, $b, $decimal_number = 2) {
        if (!is_numeric($b) || empty($b) || $b == 0 || $b == 0.0)
            return 0;
        else return round(($a/$b)*100,$decimal_number);
    }
    
    function get_max_file_upload(){
        $u_bytes = ini_get( 'upload_max_filesize' );
        $p_bytes = ini_get( 'post_max_size' );
        $data=array();
        
        $data['maxbytes']=$this->return_bytes(min($u_bytes, $p_bytes));
        $data['maxmegas'] = apply_filters( 'upload_size_limit', min($u_bytes, $p_bytes), $u_bytes, $p_bytes );
        $data['maxchars'] =(int)floor(($p_bytes*1024*1024)/200);
        return $data;
    }
    
    function return_bytes($size_str)
    {
        switch (substr ($size_str, -1))
        {
            case 'M': case 'm': return (int)$size_str * 1048576;
            case 'K': case 'k': return (int)$size_str * 1024;
            case 'G': case 'g': return (int)$size_str * 1073741824;
            default: return $size_str;
        }
    }
 
}