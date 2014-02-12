<?php
if ( !in_array('IcanSnoopy', get_declared_classes() ) ) :
/*************************************************

Snoopy - the PHP net client
Author: Monte Ohrt <monte@ispi.net>
Copyright (c): 1999-2008 New Digital Group, all rights reserved
Version: 1.2.4

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

You may contact the author of Snoopy by e-mail at:
monte@ohrt.com

The latest version of Snoopy can be obtained from:
http://snoopy.sourceforge.net/

*************************************************/

class IcanSnoopy
{
    /**** Public variables ****/

    /* user definable vars */

    var $host            =    "www.php.net";        // host name we are connecting to
    var $port            =    80;                    // port we are connecting to
    var $proxy_host        =    "";                    // proxy host to use
    var $proxy_port        =    "";                    // proxy port to use
    var $proxy_user        =    "";                    // proxy user to use
    var $proxy_pass        =    "";                    // proxy password to use

    var $agent            =    "Snoopy v1.2.4";    // agent we masquerade as
    var    $referer        =    "";                    // referer info to pass
    var $cookies        =    array();            // array of cookies to pass
                                                // $cookies["username"]="joe";
    var    $rawheaders        =    array();            // array of raw headers to send
                                                // $rawheaders["Content-type"]="text/html";

    var $maxredirs        =    5;                    // http redirection depth maximum. 0 = disallow
    var $lastredirectaddr    =    "";                // contains address of last redirected address
    var    $offsiteok        =    true;                // allows redirection off-site
    var $maxframes        =    0;                    // frame content depth maximum. 0 = disallow
    var $expandlinks    =    true;                // expand links to fully qualified URLs.
                                                // this only applies to fetchlinks()
                                                // submitlinks(), and submittext()
    var $passcookies    =    true;                // pass set cookies back through redirects
                                                // NOTE: this currently does not respect
                                                // dates, domains or paths.

    var    $user            =    "";                    // user for http authentication
    var    $pass            =    "";                    // password for http authentication

    // http accept types
    var $accept            =    "image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";

    var $results        =    "";                    // where the content is put

    var $error            =    "";                    // error messages sent here
    var    $response_code    =    "";                    // response code returned from server
    var    $headers        =    array();            // headers returned from server sent here
    var    $maxlength        =    500000;                // max return data length (body)
    var $read_timeout    =    0;                    // timeout on read operations, in seconds
                                                // supported only since PHP 4 Beta 4
                                                // set to 0 to disallow timeouts
    var $timed_out        =    false;                // if a read operation timed out
    var    $status            =    0;                    // http request status

    var $temp_dir        =    "/tmp";                // temporary directory that the webserver
                                                // has permission to write to.
                                                // under Windows, this should be C:\temp

    var    $curl_path        =    "/usr/local/bin/curl";
                                                // Snoopy will use cURL for fetching
                                                // SSL content if a full system path to
                                                // the cURL binary is supplied here.
                                                // set to false if you do not have
                                                // cURL installed. See http://curl.haxx.se
                                                // for details on installing cURL.
                                                // Snoopy does *not* use the cURL
                                                // library functions built into php,
                                                // as these functions are not stable
                                                // as of this Snoopy release.

    /**** Private variables ****/

    var    $_maxlinelen    =    4096;                // max line length (headers)

    var $_httpmethod    =    "GET";                // default http request method
    var $_httpversion    =    "HTTP/1.0";            // default http request version
    var $_submit_method    =    "POST";                // default submit method
    var $_submit_type    =    "application/x-www-form-urlencoded";    // default submit type
    var $_mime_boundary    =   "";                    // MIME boundary for multipart/form-data submit type
    var $_redirectaddr    =    false;                // will be set if page fetched is a redirect
    var $_redirectdepth    =    0;                    // increments on an http redirect
    var $_frameurls        =     array();            // frame src urls
    var $_framedepth    =    0;                    // increments on frame depth

    var $_isproxy        =    false;                // set if using a proxy server
    var $_fp_timeout    =    30;                    // timeout for socket connection

/*======================================================================*\
    Function:    fetch
    Purpose:    fetch the contents of a web page
                (and possibly other protocols in the
                future like ftp, nntp, gopher, etc.)
    Input:        $URI    the location of the page to fetch
    Output:        $this->results    the output text from the fetch
\*======================================================================*/

    function fetch($URI)
    {

        //preg_match("|^([^:]+)://([^:/]+)(:[\d]+)*(.*)|",$URI,$URI_PARTS);
        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            $this->user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            $this->pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        switch(strtolower($URI_PARTS["scheme"]))
        {
            case "http":
                $this->host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if($this->_connect($fp))
                {
                    if($this->_isproxy)
                    {
                        // using proxy, send entire URI
                        $this->_httprequest($URI,$fp,$URI,$this->_httpmethod);
                    }
                    else
                    {
                        $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                        // no proxy, send only the path
                        $this->_httprequest($path, $fp, $URI, $this->_httpmethod);
                    }

                    $this->_disconnect($fp);

                    if($this->_redirectaddr)
                    {
                        /* url was redirected, check if we've hit the max depth */
                        if($this->maxredirs > $this->_redirectdepth)
                        {
                            // only follow redirect if it's on this site, or offsiteok is true
                            if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
                            {
                                /* follow the redirect */
                                $this->_redirectdepth++;
                                $this->lastredirectaddr=$this->_redirectaddr;
                                $this->fetch($this->_redirectaddr);
                            }
                        }
                    }

                    if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
                    {
                        $frameurls = $this->_frameurls;
                        $this->_frameurls = array();

                        while(list(,$frameurl) = each($frameurls))
                        {
                            if($this->_framedepth < $this->maxframes)
                            {
                                $this->fetch($frameurl);
                                $this->_framedepth++;
                            }
                            else
                                break;
                        }
                    }
                }
                else
                {
                    return false;
                }
                return true;
                break;
            case "https":
                if(!$this->curl_path)
                    return false;
                
                // disable error reporting
                // needed for open_basedir restrictions (is_readable)
                $_display_errors = ini_get('display_errors');
                $_error_reporting = ini_get('error_reporting');
                ini_set('display_errors', '0');        
                ini_set('error_reporting', E_NONE);        
                
                $_test = function_exists("is_executable") && (!@is_readable($this->curl_path) || !@is_executable($this->curl_path));
                
                // restore error reporting
                // needed for open_basedir restrictions
                ini_set('display_errors', $_display_errors);        
                ini_set('error_reporting', $_error_reporting);        
                
                if($_test) return false;
                
                $this->host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if($this->_isproxy)
                {
                    // using proxy, send entire URI
                    $this->_httpsrequest($URI,$URI,$this->_httpmethod);
                }
                else
                {
                    $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                    // no proxy, send only the path
                    $this->_httpsrequest($path, $URI, $this->_httpmethod);
                }

                if($this->_redirectaddr)
                {
                    /* url was redirected, check if we've hit the max depth */
                    if($this->maxredirs > $this->_redirectdepth)
                    {
                        // only follow redirect if it's on this site, or offsiteok is true
                        if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
                        {
                            /* follow the redirect */
                            $this->_redirectdepth++;
                            $this->lastredirectaddr=$this->_redirectaddr;
                            $this->fetch($this->_redirectaddr);
                        }
                    }
                }

                if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
                {
                    $frameurls = $this->_frameurls;
                    $this->_frameurls = array();

                    while(list(,$frameurl) = each($frameurls))
                    {
                        if($this->_framedepth < $this->maxframes)
                        {
                            $this->fetch($frameurl);
                            $this->_framedepth++;
                        }
                        else
                            break;
                    }
                }
                return true;
                break;
            default:
                // not a valid protocol
                $this->error    =    'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
                return false;
                break;
        }
        return true;
    }

/*======================================================================*\
    Function:    submit
    Purpose:    submit an http form
    Input:        $URI    the location to post the data
                $formvars    the formvars to use.
                    format: $formvars["var"] = "val";
                $formfiles  an array of files to submit
                    format: $formfiles["var"] = "/dir/filename.ext";
    Output:        $this->results    the text output from the post
\*======================================================================*/

    function submit($URI, $formvars="", $formfiles="")
    {
        unset($postdata);

        $postdata = $this->_prepare_post_body($formvars, $formfiles);

        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            $this->user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            $this->pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        switch(strtolower($URI_PARTS["scheme"]))
        {
            case "http":
                $this->host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if($this->_connect($fp))
                {
                    if($this->_isproxy)
                    {
                        // using proxy, send entire URI
                        $this->_httprequest($URI,$fp,$URI,$this->_submit_method,$this->_submit_type,$postdata);
                    }
                    else
                    {
                        $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                        // no proxy, send only the path
                        $this->_httprequest($path, $fp, $URI, $this->_submit_method, $this->_submit_type, $postdata);
                    }

                    $this->_disconnect($fp);

                    if($this->_redirectaddr)
                    {
                        /* url was redirected, check if we've hit the max depth */
                        if($this->maxredirs > $this->_redirectdepth)
                        {
                            if(!preg_match("|^".$URI_PARTS["scheme"]."://|", $this->_redirectaddr))
                                $this->_redirectaddr = $this->_expandlinks($this->_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

                            // only follow redirect if it's on this site, or offsiteok is true
                            if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
                            {
                                /* follow the redirect */
                                $this->_redirectdepth++;
                                $this->lastredirectaddr=$this->_redirectaddr;
                                if( strpos( $this->_redirectaddr, "?" ) > 0 )
                                    $this->fetch($this->_redirectaddr); // the redirect has changed the request method from post to get
                                else
                                    $this->submit($this->_redirectaddr,$formvars, $formfiles);
                            }
                        }
                    }

                    if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
                    {
                        $frameurls = $this->_frameurls;
                        $this->_frameurls = array();

                        while(list(,$frameurl) = each($frameurls))
                        {
                            if($this->_framedepth < $this->maxframes)
                            {
                                $this->fetch($frameurl);
                                $this->_framedepth++;
                            }
                            else
                                break;
                        }
                    }

                }
                else
                {
                    return false;
                }
                return true;
                break;
            case "https":
                if(!$this->curl_path)
                    return false;

                // disable error reporting
                // needed for open_basedir restrictions (is_readable)
                $_display_errors = ini_get('display_errors');
                $_error_reporting = ini_get('error_reporting');
                ini_set('display_errors', '0');        
                ini_set('error_reporting', E_NONE);        
                
                $_test = function_exists("is_executable") && (!@is_readable($this->curl_path) || !@is_executable($this->curl_path));
                
                // restore error reporting
                // needed for open_basedir restrictions
                ini_set('display_errors', $_display_errors);        
                ini_set('error_reporting', $_error_reporting);        
                
                if($_test) return false;
                    
                $this->host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    $this->port = $URI_PARTS["port"];
                if($this->_isproxy)
                {
                    // using proxy, send entire URI
                    $this->_httpsrequest($URI, $URI, $this->_submit_method, $this->_submit_type, $postdata);
                }
                else
                {
                    $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                    // no proxy, send only the path
                    $this->_httpsrequest($path, $URI, $this->_submit_method, $this->_submit_type, $postdata);
                }

                if($this->_redirectaddr)
                {
                    /* url was redirected, check if we've hit the max depth */
                    if($this->maxredirs > $this->_redirectdepth)
                    {
                        if(!preg_match("|^".$URI_PARTS["scheme"]."://|", $this->_redirectaddr))
                            $this->_redirectaddr = $this->_expandlinks($this->_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

                        // only follow redirect if it's on this site, or offsiteok is true
                        if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
                        {
                            /* follow the redirect */
                            $this->_redirectdepth++;
                            $this->lastredirectaddr=$this->_redirectaddr;
                            if( strpos( $this->_redirectaddr, "?" ) > 0 )
                                $this->fetch($this->_redirectaddr); // the redirect has changed the request method from post to get
                            else
                                $this->submit($this->_redirectaddr,$formvars, $formfiles);
                        }
                    }
                }

                if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
                {
                    $frameurls = $this->_frameurls;
                    $this->_frameurls = array();

                    while(list(,$frameurl) = each($frameurls))
                    {
                        if($this->_framedepth < $this->maxframes)
                        {
                            $this->fetch($frameurl);
                            $this->_framedepth++;
                        }
                        else
                            break;
                    }
                }
                return true;
                break;

            default:
                // not a valid protocol
                $this->error    =    'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
                return false;
                break;
        }
        return true;
    }

/*======================================================================*\
    Function:    fetchlinks
    Purpose:    fetch the links from a web page
    Input:        $URI    where you are fetching from
    Output:        $this->results    an array of the URLs
\*======================================================================*/

    function fetchlinks($URI)
    {
        if ($this->fetch($URI))
        {
            if($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if(is_array($this->results))
            {
                for($x=0;$x<count($this->results);$x++)
                    $this->results[$x] = $this->_striplinks($this->results[$x]);
            }
            else
                $this->results = $this->_striplinks($this->results);

            if($this->expandlinks)
                $this->results = $this->_expandlinks($this->results, $URI);
            return true;
        }
        else
            return false;
    }

/*======================================================================*\
    Function:    fetchform
    Purpose:    fetch the form elements from a web page
    Input:        $URI    where you are fetching from
    Output:        $this->results    the resulting html form
\*======================================================================*/

    function fetchform($URI)
    {

        if ($this->fetch($URI))
        {

            if(is_array($this->results))
            {
                for($x=0;$x<count($this->results);$x++)
                    $this->results[$x] = $this->_stripform($this->results[$x]);
            }
            else
                $this->results = $this->_stripform($this->results);

            return true;
        }
        else
            return false;
    }


/*======================================================================*\
    Function:    fetchtext
    Purpose:    fetch the text from a web page, stripping the links
    Input:        $URI    where you are fetching from
    Output:        $this->results    the text from the web page
\*======================================================================*/

    function fetchtext($URI)
    {
        if($this->fetch($URI))
        {
            if(is_array($this->results))
            {
                for($x=0;$x<count($this->results);$x++)
                    $this->results[$x] = $this->_striptext($this->results[$x]);
            }
            else
                $this->results = $this->_striptext($this->results);
            return true;
        }
        else
            return false;
    }

/*======================================================================*\
    Function:    submitlinks
    Purpose:    grab links from a form submission
    Input:        $URI    where you are submitting from
    Output:        $this->results    an array of the links from the post
\*======================================================================*/

    function submitlinks($URI, $formvars="", $formfiles="")
    {
        if($this->submit($URI,$formvars, $formfiles))
        {
            if($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if(is_array($this->results))
            {
                for($x=0;$x<count($this->results);$x++)
                {
                    $this->results[$x] = $this->_striplinks($this->results[$x]);
                    if($this->expandlinks)
                        $this->results[$x] = $this->_expandlinks($this->results[$x],$URI);
                }
            }
            else
            {
                $this->results = $this->_striplinks($this->results);
                if($this->expandlinks)
                    $this->results = $this->_expandlinks($this->results,$URI);
            }
            return true;
        }
        else
            return false;
    }

/*======================================================================*\
    Function:    submittext
    Purpose:    grab text from a form submission
    Input:        $URI    where you are submitting from
    Output:        $this->results    the text from the web page
\*======================================================================*/

    function submittext($URI, $formvars = "", $formfiles = "")
    {
        if($this->submit($URI,$formvars, $formfiles))
        {
            if($this->lastredirectaddr)
                $URI = $this->lastredirectaddr;
            if(is_array($this->results))
            {
                for($x=0;$x<count($this->results);$x++)
                {
                    $this->results[$x] = $this->_striptext($this->results[$x]);
                    if($this->expandlinks)
                        $this->results[$x] = $this->_expandlinks($this->results[$x],$URI);
                }
            }
            else
            {
                $this->results = $this->_striptext($this->results);
                if($this->expandlinks)
                    $this->results = $this->_expandlinks($this->results,$URI);
            }
            return true;
        }
        else
            return false;
    }



/*======================================================================*\
    Function:    set_submit_multipart
    Purpose:    Set the form submission content type to
                multipart/form-data
\*======================================================================*/
    function set_submit_multipart()
    {
        $this->_submit_type = "multipart/form-data";
    }


/*======================================================================*\
    Function:    set_submit_normal
    Purpose:    Set the form submission content type to
                application/x-www-form-urlencoded
\*======================================================================*/
    function set_submit_normal()
    {
        $this->_submit_type = "application/x-www-form-urlencoded";
    }




/*======================================================================*\
    Private functions
\*======================================================================*/


/*======================================================================*\
    Function:    _striplinks
    Purpose:    strip the hyperlinks from an html document
    Input:        $document    document to strip.
    Output:        $match        an array of the links
\*======================================================================*/

    function _striplinks($document)
    {
        preg_match_all("'<\s*a\s.*?href\s*=\s*            # find <a href=
                        ([\"\'])?                    # find single or double quote
                        (?(1) (.*?)\\1 | ([^\s\>]+))        # if quote found, match up to next matching
                                                    # quote, otherwise match up to next space
                        'isx",$document,$links);


        // catenate the non-empty matches from the conditional subpattern

        while(list($key,$val) = each($links[2]))
        {
            if(!empty($val))
                $match[] = $val;
        }

        while(list($key,$val) = each($links[3]))
        {
            if(!empty($val))
                $match[] = $val;
        }

        // return the links
        return $match;
    }

/*======================================================================*\
    Function:    _stripform
    Purpose:    strip the form elements from an html document
    Input:        $document    document to strip.
    Output:        $match        an array of the links
\*======================================================================*/

    function _stripform($document)
    {
        preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi",$document,$elements);

        // catenate the matches
        $match = implode("\r\n",$elements[0]);

        // return the links
        return $match;
    }



/*======================================================================*\
    Function:    _striptext
    Purpose:    strip the text from an html document
    Input:        $document    document to strip.
    Output:        $text        the resulting text
\*======================================================================*/

    function _striptext($document)
    {

        // I didn't use preg eval (//e) since that is only available in PHP 4.0.
        // so, list your entities one by one here. I included some of the
        // more common ones.

        $search = array("'<script[^>]*?>.*?</script>'si",    // strip out javascript
                        "'<[\/\!]*?[^<>]*?>'si",            // strip out html tags
                        "'([\r\n])[\s]+'",                    // strip out white space
                        "'&(quot|#34|#034|#x22);'i",        // replace html entities
                        "'&(amp|#38|#038|#x26);'i",            // added hexadecimal values
                        "'&(lt|#60|#060|#x3c);'i",
                        "'&(gt|#62|#062|#x3e);'i",
                        "'&(nbsp|#160|#xa0);'i",
                        "'&(iexcl|#161);'i",
                        "'&(cent|#162);'i",
                        "'&(pound|#163);'i",
                        "'&(copy|#169);'i",
                        "'&(reg|#174);'i",
                        "'&(deg|#176);'i",
                        "'&(#39|#039|#x27);'",
                        "'&(euro|#8364);'i",                // europe
                        "'&a(uml|UML);'",                    // german
                        "'&o(uml|UML);'",
                        "'&u(uml|UML);'",
                        "'&A(uml|UML);'",
                        "'&O(uml|UML);'",
                        "'&U(uml|UML);'",
                        "'&szlig;'i",
                        );
        $replace = array(    "",
                            "",
                            "\\1",
                            "\"",
                            "&",
                            "<",
                            ">",
                            " ",
                            chr(161),
                            chr(162),
                            chr(163),
                            chr(169),
                            chr(174),
                            chr(176),
                            chr(39),
                            chr(128),
