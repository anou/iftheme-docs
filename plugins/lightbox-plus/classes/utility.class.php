<?php
    /**
    * @package Lightbox Plus Colorbox
    * @subpackage utility.class.php
    * @internal 2013.01.16
    * @author Dan Zappone / 23Systems
    * @version 2.7
    * @$Id: utility.class.php 937945 2014-06-24 17:11:13Z dzappone $
    * @$URL: http://plugins.svn.wordpress.org/lightbox-plus/tags/2.7/classes/utility.class.php $
    */
    if (!class_exists('lbp_utilities')) {

        /**
        * Lightbox Plus Colorbox Utiltiy Functions used throughout plugin
        *
        * Not sure if WordPress has equivelents but cannot locate in API docs if so
        */
        class lbp_utilities {
            /**
            * Create clean eols for source
            *
            * @return string
            */
//            function EOL( ) {
//                switch ( strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
//                    case 'WIN':
//                        return "\r\n";
//                        break;
//                    case 'MAC':
//                        return "\r";
//                        break;
//                    default:
//                        return "\n";
//                        break;
//                }
//            }

            /**
            * Create dropdown name from stylesheet listing - make user friendly
            *
            * @param mixed $styleName
            * @return string
            */
            function setProperName( $styleName ) {
                $styleName = str_replace( '.css', '', $styleName );
                $styleName = ucfirst( $styleName );
                return $styleName;
            }

            function setLoadLocation( $loadlocation ) {
                if ($loadlocation == 'wp_head') {
                    return false;
                    exit;                    
                } else {
                    return true;
                    exit;                    
                }
            }

            function setMissingOptions( $lbpOptionsArray ) {
                /**
                * Remove following 3 lines after a few versions or 2.6 is the prevelent version
                */
                if (!isset($lbpOptionsArray['output_htmlv']) || (array_key_exists('output_htmlv', $lbpOptionsArray) == false) ) { $lbpOptionsArray['output_htmlv'] = '0'; $lbpOptionsArray['data_name'] = 'lightboxplus'; }
                if (!isset($lbpOptionsArray['load_location']) || (array_key_exists('load_location', $lbpOptionsArray) == false) ) { $lbpOptionsArray['load_location'] = 'wp_footer'; }
                if (!isset($lbpOptionsArray['load_priority']) || (array_key_exists('load_priority', $lbpOptionsArray) == false) ) { $lbpOptionsArray['load_priority'] = '10'; }
                return $lbpOptionsArray;
            }

            
            /**
            * Convert DB booleans to text for use with JavaScript (jQuery) parameters
            *
            * @param mixed $nValue
            * @return mixed
            */
            function setBoolean( $nValue ) {
                switch ( $nValue ) {
                    case 1:
                        return 'true';
                        break;
                    default:
                        return 'false';
                        break;
                }
            }

            /**
            * Convert DB booleans to text for use with JavaScript (jQuery) parameters
            *
            * @param mixed $rValue
            */
            function setValue( $rValue ) {
                if ($rValue == '' || $rValue == 'false') {
                    $tmpValue = 'false';
                } else {
                    $tmpValue = '"'.$rValue.'"';
                }
                return $tmpValue;
            }

            /**
            * Delete directory function used to remove old directories during upgrade from versions prior to 1.4
            *
            * @param mixed $dirname
            */
            function delete_directory( $dirname ) {
                if ( is_dir( $dirname ) ) {
                    $dir_handle = opendir( $dirname );
                }
                if ( !$dir_handle ) {
                    return false;
                }
                while ( $file = readdir( $dir_handle ) ) {
                    if ( $file != '.' && $file != '..' ) {
                        if ( !is_dir( $dirname.'/'.$file ) ) {
                            unlink( $dirname.'/'.$file );
                        } else {
                            delete_directory( $dirname.'/'.$file );
                        }
                    }
                }
                closedir( $dir_handle );
                rmdir( $dirname );
                return true;
            }

            /**
            * Delete directory function used to remove old directories during upgrade from versions prior to 1.4
            *
            * @param mixed $dirname
            * @param mixed $file
            */
            function delete_file( $dirname, $file ) {
                if ( $file != '.' && $file != '..' ) {
                    if ( !is_dir( $dirname.'/'.$file ) ) {
                        unlink( $dirname.'/'.$file );
                    }
                    return true;
                }
            }

            /**
            * List directory function used to iterate theme directories
            *
            * @param mixed $dirname
            */
            function dirList( $dirname ) {
                $types = array(
                'css',
                );
                $results = array( );
                $dir_handle = opendir( $dirname );
                while ( $file = readdir( $dir_handle ) ) {
                    $type = strtolower( substr( strrchr( $file, '.' ), 1 ) );
                    if ( in_array( $type, $types ) ) {
                        array_push( $results, $file );
                    }
                }
                closedir( $dir_handle );
                sort( $results );
                return $results;
            }

            /**
            * Used to do a boolean check against PHP version
            *
            * @param mixed $v
            */
            function phpMinV($v) {
                $phpV = PHP_VERSION;

                if ($phpV[0] >= $v[0]) {
                    if (empty($v[2]) || $v[2] == '*') {
                        return true;
                    }
                    elseif ($phpV[2] >= $v[2]) {
                        if (empty($v[4]) || $v[4] == '*' || $phpV[4] >= $v[4]) {
                            return true;
                        }
                    }
                }
                return false;
            }

            /**
            * Recursively copy a directory
            *
            * @param mixed $source
            * @param mixed $destination
            */
            function copy_directory( $source, $destination ) {
                if ( is_dir( $source ) ) {
                    @mkdir( $destination );
                    $directory = dir( $source );
                    while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
                        if ( $readdirectory == '.' || $readdirectory == '..' ) {
                            continue;
                        }
                        $PathDir = $source . '/' . $readdirectory;
                        if ( is_dir( $PathDir ) ) {
                            $this->copy_directory( $PathDir, $destination . '/' . $readdirectory );
                            continue;
                        }
                        copy( $PathDir, $destination . '/' . $readdirectory );
                    }

                    $directory->close();
                }
                else {
                    copy( $source, $destination );
                }
            }

            function post_thumbnail_caption() {
                extract(shortcode_atts(array(
                'id'      => '',
                'align'   => 'alignnone',
                'width'   => '',
                'caption' => ''
                ), $attr));

                return $caption;
            }
        }
    }
?>