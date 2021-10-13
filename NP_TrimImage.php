<?php
// vim: tabstop=2:shiftwidth=2

/**
  * NP_TrimImage ($Revision: 1.68 $)
  * by nakahara21 ( http://nakahara21.com/ )
  * by hsur ( http://blog.cles.jp/np_cles/ )
  * $Id: NP_TrimImage.php,v 1.68 2008/12/22 05:47:24 hsur Exp $
  *
  * Based on NP_TrimImage 1.0 by nakahara21
  * http://nakahara21.com/?itemid=512
*/

/*
  * Copyright (C) 2004-2006 nakahara21 All rights reserved.
  * Copyright (C) 2006-2008 cles All rights reserved.
*/

define('NP_TRIMIMAGE_FORCE_PASSTHRU', true); //passthru(standard)
//define('NP_TRIMIMAGE_FORCE_PASSTHRU', false); //redirect(advanced)

define('NP_TRIMIMAGE_CACHE_MAXAGE', 86400 * 30); // 30days
define('NP_TRIMIMAGE_PREFER_IMAGEMAGICK', false);

class NP_TrimImage extends NucleusPlugin {
	function getName() {
		return 'TrimImage';
	}

	function getAuthor() {
		return 'nakahara21 + hsur';
	}

	function getURL() {
		return 'https://github.com/NucleusCMS/NP_TrimImage';
	}

	function getVersion() {
		return '2.5';
	}

	function supportsFeature($what) {return in_array($what,array('SqlTablePrefix','SqlApi'));}

	function getDescription() {
		return 'Trim image in items, and embed these images.';
	}
	
	function getEventList() {
		return array ('PostAddItem', 'PostUpdateItem', 'PostDeleteItem',);
	}
	
	function event_PostAddItem(& $data) {
		$this->_clearCache();
	}
	function event_PostUpdateItem(& $data) {
		$this->_clearCache();
	}
	function event_PostDeleteItem(& $data) {
		$this->_clearCache();
	}
	function _clearCache() {
/*
		$phpThumb = new phpThumb();
		foreach ($this->phpThumbParams as $paramKey => $paramValue) {
			$phpThumb->setParameter($paramKey, $paramValue);
		}
		$phpThumb->setParameter('config_cache_maxage', 1);
		$phpThumb->CleanUpCacheDirectory();
		var_dump($phpThumb);
*/
	}

	function init() {
		global $DIR_MEDIA;
		$this->fileex = array ('.gif', '.jpg', '.png');
		$cacheDir = $DIR_MEDIA.'phpthumb/';
		$cacheDir = (is_dir($cacheDir) && @ is_writable($cacheDir)) ? $cacheDir : null;
		
		$this->phpThumbParams = array(
			'config_document_root' => $DIR_MEDIA,
			'config_cache_directory' => $cacheDir,
			'config_cache_disable_warning' => true,
			'config_cache_directory_depth' => 0,
			'config_cache_maxage' => NP_TRIMIMAGE_CACHE_MAXAGE,
			'config_cache_maxsize' => 10 * 1024 * 1024, // 10MB
			'config_cache_maxfiles' => 1000,
			'config_cache_source_filemtime_ignore_local' => true,
			'config_cache_cache_default_only_suffix' => '',
			'config_cache_prefix' => 'phpThumb_cache',
			'config_cache_force_passthru' => NP_TRIMIMAGE_FORCE_PASSTHRU,
			'config_max_source_pixels' => 3871488, //4Mpx
			'config_output_format' => 'jpg',
			'config_disable_debug' => true,
			'config_prefer_imagemagick' => NP_TRIMIMAGE_PREFER_IMAGEMAGICK,
		);
	}

	function getCategoryIDFromItemID($itemid) {
		return quickQuery('SELECT icat as result FROM ' . sql_table('item') . ' WHERE inumber=' . intval($itemid) );
	}
	
	function doSkinVar($skinType, $amount = 10, $wsize = 80, $hsize = 80, $point = 0, $random = 0, $exmode = '', $titlemode = '', $includeImg = 'true') {
		global $CONF, $manager, $blog;
		if ($blog) {
			$b = & $blog;
		} else {
			$b = & $manager->getBlog($CONF['DefaultBlog']);
		}
		
		if(strpos($amount,'/')!==false) {
            list($amount, $maxPerItem) = explode('/', $amount, 2);
        } else {
            $maxPerItem = 0;
        }
		if (!is_numeric($amount))      $amount = 10;
		if (!is_numeric($hsize))       $hsize = 80;
		if (!is_numeric($wsize))       $wsize = 80;
		if (!is_numeric($maxPerItem)) $maxPerItem = 0;
		$point = $point === 'lefttop';
		$includeImg = $includeImg === 'true';
		
		$this->exquery = '';

		switch ($skinType) {
			case 'archive' :
				global $archive;
				$year = $month = $day = '';
				sscanf($archive, '%d-%d-%d', $year, $month, $day);
				if (empty ($day)) {
					$timestamp_start = mktime(0, 0, 0, $month, 1, $year);
					$timestamp_end = mktime(0, 0, 0, $month+1, 1, $year); // also works when $month==12
				} else {
					$timestamp_start = mktime(0, 0, 0, $month, $day, $year);
					$timestamp_end = mktime(0, 0, 0, $month, $day+1, $year);
				}
				$this->exquery .= sprintf(
				    ' and itime >= %s and itime < %s',
                    mysqldate($timestamp_start),
                    mysqldate($timestamp_end)
                );

				//break;
			default :
                if ($exmode == '' || $exmode === 'itemcat') {
                    global $catid, $itemid;
                    if ($catid) {
                        $this->exquery .= ' and icat = ' . (int)$catid;
                    } elseif ($exmode === 'itemcat' && $itemid) {
                        $this->exquery .= ' and icat = ' . (int)$this->getCategoryIDFromItemID($itemid);
                    } else {
                        $this->exquery .= ' and iblog = ' . (int)$b->getID();
                    }
                } elseif ($exmode !== 'all') {
                    $spbid = $spcid = array();
                    $spid_array = explode('/', $exmode);
                    foreach ($spid_array as $spid) {
                        $type = substr($spid, 0, 1);
                        $type_id = (int)substr($spid, 1);
                        if (!$type || !$type_id) {
                            continue;
                        }
                        switch ($type) {
                            case 'b':
                                $spbid[] = $type_id;
                                break;
                            case 'c':
                                $spcid[] = $type_id;
                                break;
                        }
                    }
                    if ($spbid) {
                        $this->exquery .= sprintf(' AND iblog IN (%s) ', implode(',', $spbid));
                    }
                    if ($spcid) {
                        $this->exquery .= sprintf(" AND icat IN (%s) ", implode(',', $spcid));
                    }
                }
		}

		$this->imglists = array ();
		$this->imgfilename = array ();
		$random = (bool)$random;
        $filelist = $this->_listup($amount, $random, $includeImg, $maxPerItem);
		if (!$filelist) {
            return;
        }

        $amount = min($amount, count($filelist));
		echo '<div>';
		for ($i = 0; $i < $amount; $i ++) {
			$itemlink = createItemLink($filelist[$i][1]);
			echo '<a href="'.$itemlink.'">';

			$src = '';
			if (!$this->phpThumbParams['config_cache_force_passthru']) {
				$src = $this->createImage($filelist[$i][0], $wsize, $hsize, $point, true);
			}
			if (!$src) {
				$src = sprintf(
				    '%s?action=plugin&amp;name=TrimImage&amp;type=draw&amp;p=%s&amp;wsize=%d&amp;hsize=%s%s',
                    hsc($CONF['ActionURL'], ENT_QUOTES),
                    urlencode($filelist[$i][0]),
                    $wsize,
                    $hsize,
                    $point ? '&amp;pnt=lefttop' : ''
                );
			}
			
			if($titlemode === 'item') {
                $title = ($filelist[$i][4]) ? $filelist[$i][4] : $filelist[$i][2];
            } else {
                $title = ($filelist[$i][2]) ? $filelist[$i][2] : $filelist[$i][4];
            }

			echo sprintf(
			    '<img src="%s" %s %s alt="%s" title="%s"/>',
                $src,
                $wsize ? sprintf(' width="%s" ', $wsize) : '',
                $hsize ? sprintf(' height="%s" ', $hsize) : '',
                hsc($title, ENT_QUOTES),
                hsc($title, ENT_QUOTES)
            );
			echo "</a>\n";
		}
		echo "</div>\n";
	}

	function _listup($amount = 10, $random = false, $includeImg = true, $maxPerItem = 0) {
		global $CONF, $manager, $blog;
		if ($blog) {
			$b = & $blog;
		} else {
			$b = & $manager->getBlog($CONF['DefaultBlog']);
		}

		$query = 'SELECT inumber as itemid, ititle as title, ibody as body, iauthor, itime, imore as more,';
		$query .= ' icat as catid, iclosed as closed';
		$query .= ' FROM '.sql_table('item');
		$query .= ' WHERE idraft = 0';
		$query .= ' and itime <= '.mysqldate($b->getCorrectTime()); // don't show future items!
		$query .= ' ' . $this->exquery;
		$query .= ' ORDER BY itime DESC LIMIT '. (int)($amount * 10);

		$res = sql_query($query);

		if (!sql_num_rows($res)) {
            return false;
        }

		while ($it = sql_fetch_object($res)) {
			$this->_parseItem($it, $maxPerItem, $includeImg);
			
			if (count($this->imglists) >= $amount) {
                break;
            }
		}
		sql_free_result($res);

		if ($random) {
            shuffle($this->imglists);
        }
		$this->imglists = array_slice($this->imglists, 0, $amount);
		return $this->imglists;
	}
	
	function _parseItem(&$item, $maxPerItem = 0, $includeImg = true) {
		if($includeImg){
			$pattern = '/(<%(image|popup|paint)\((.*?)\)%>)|(<img\s(.*?)>)/s';
		} else {
            $pattern = '/(<%(image|popup|paint)\((.*?)\)%>)/s';
        }
		
		if (!preg_match_all($pattern, $item->body . $item->more, $matched)) {
            return;
        }
        if ($maxPerItem) {
            array_splice($matched[3], $maxPerItem); // nucleus images attribute
        }
        foreach ($matched[3] as $index => $imgAttribute) {
            if ($imgAttribute) {
                $this->_parseImageTag($imgAttribute, $item, false);
            } else {
                $this->_parseImageTag($matched[5][$index], $item, true);
            }
        }
    }

	function _parseImageTag($imginfo, &$item, $isImg) {
		global $CONF;
		if ($isImg){
			if(!preg_match_all('/(src|width|height|alt|title)=\"(.*?)\"/i', $imginfo, $matches)) {
                return;
            }
            $param = array();
            foreach ($matches[1] as $index => $type) {
                $param[$type] = $matches[2][$index];
            }
            if (!isset($param['title'])) {
                $param['title'] = '';
            }
            if (!isset($param['width'])) {
                $param['width'] = '';
            }
            if (!isset($param['height'])) {
                $param['height'] = '';
            }
            if (!isset($param['alt'])) {
                $param['alt'] = '';
            }

            if (strpos($CONF['MediaURL'], $CONF['IndexURL']) === 0) {
                $MediaDIR = '/' . substr($CONF['MediaURL'], strlen($CONF['IndexURL']));
            } else {
                $MediaDIR = false;
            }

            if ($param['src'] && (strpos($param['src'], $CONF['MediaURL']) === 0)) {
                $imginfo = sprintf(
                    '%s|%s|%s|%s',
                    substr($param['src'], strlen($CONF['MediaURL'])),
                    $param['width'],
                    $param['height'],
                    $param['title'] ? $param['title'] : $param['alt']
                );
            } elseif ($param['src'] && $MediaDIR && (strpos($param['src'], $MediaDIR) === 0)) {
                $imginfo = sprintf(
                    '%s|%s|%s|%s',
                    substr($param['src'], strlen($MediaDIR)),
                    $param['width'],
                    $param['height'],
                    $param['title'] ? $param['title'] : $param['alt']
                );
            }
        }
		
		if(strpos($imginfo,'|')===false) {
            return;
        }
		
		$_ = explode('|', $imginfo, 5);
		
		$url = (isset($_[0])) ? $_[0] : '';
		$w   = (isset($_[1])) ? $_[1] : '';
		$h   = (isset($_[2])) ? $_[2] : '';
		$alt = (isset($_[3])) ? $_[3] : '';
		$ext = (isset($_[4])) ? $_[4] : '';
		
		if (!in_array(strtolower(strrchr($url, ".")), $this->fileex)) {
            return;
        }
		if (in_array($url, $this->imgfilename)) {
            return;
        }
		$this->imgfilename[] = $url;
		if (strpos($url, '/') === false) {
			$url = $item->iauthor.'/'.$url;
		}
		$this->imglists[] = array ($url, $item->itemid, $alt, $ext, $item->title);
	}

	function doTemplateVar(& $item, $wsize=80, $hsize=80, $point=0, $maxAmount=0, $titlemode='', $includeImg='true') {
		global $CONF;
		if (!is_numeric($hsize)) {
            $hsize = 80;
        }
		if (!is_numeric($wsize)) {
            $wsize = 80;
        }
		$point = $point === 'lefttop';
		$includeImg = $includeImg === 'true';
		
		$this->imglists = array ();
		$this->imgfilename = array ();

		$q  = 'SELECT inumber as itemid, ititle as title, ibody as body, iauthor, itime, imore as more, ';
		$q .= 'icat as catid, iclosed as closed ';
		$q .= 'FROM '.sql_table('item').' WHERE inumber='. (int)$item->itemid;
		$it = sql_fetch_object(sql_query($q));
		$this->_parseItem($it, $maxAmount, $includeImg);

		if (!$this->imglists) {
			$img_tag = sprintf(
			    '<img src="%s?action=plugin&amp;name=TrimImage&amp;type=draw&amp;p=non&amp;wsize=%d&amp;hsize=%d" width="%s" height="%d" />',
                hsc($CONF['ActionURL'], ENT_QUOTES),
                $wsize,
                $hsize,
                $wsize,
                $hsize
            );
			echo $img_tag;
			return;
		}

        foreach($this->imglists as $img) {
            $src = '';
            if (!$this->phpThumbParams['config_cache_force_passthru']) {
                $src = $this->createImage($img[0], $wsize, $hsize, $point, true);
            }
            if (!$src) {
                $src = sprintf(
                    '%s?action=plugin&amp;name=TrimImage&amp;type=draw&amp;p=%s&amp;wsize=%d&amp;hsize=%s%s',
                    hsc($CONF['ActionURL'], ENT_QUOTES),
                    urlencode($img[0]),
                    $wsize,
                    $hsize,
                    $point ? '&amp;pnt=lefttop' : ''
                );
            }

            $title = ($img[2]) ? $img[2] : $img[4];
            if($titlemode === 'item') {
                $title = ($img[4]) ? $img[4] : $img[2];
            }

            echo sprintf(
                '<img src="%s" %s%s alt="%s" title="%s" />',
                $src,
                $wsize ? 'width="' . $wsize . '" ' : '',
                $hsize ? 'height="' . $hsize . '" ' : '',
                hsc($title, ENT_QUOTES),
                hsc($title, ENT_QUOTES)
            );
        }
    }

	function doAction($type) {
        if ($type !== 'draw') {
            return 'No such action';
        }
        $this->createImage(
            requestVar('p'),
            is_numeric(requestVar('wsize')) ? requestVar('wsize') : 80,
            is_numeric(requestVar('hsize')) ? requestVar('hsize') : 80,
            requestVar('pnt') === 'lefttop'
        );
        return null;
    }

	function createImage($p, $w, $h, $isLefttop, $cacheCheckOnly = false) {
		$plg_path = $this->getDirectory();
		require_once($plg_path.'phpthumb/phpthumb.functions.php');
		require_once($plg_path.'phpthumb/phpthumb.class.php');
		$phpThumb = new phpThumb();
		foreach ($this->phpThumbParams as $paramKey => $paramValue) {
			$phpThumb->setParameter($paramKey, $paramValue);
		}

		if($h) {
            $phpThumb->setParameter('h', (int)$h);
        }
		if($w) {
            $phpThumb->setParameter('w', (int)$w);
        }

		if ($p === 'non') {
            $phpThumb->gdimg_source = phpthumb_functions::ImageCreateFunction($phpThumb->w, $phpThumb->h);
			if ($phpThumb->gdimg_source) {
				$phpThumb->setParameter('is_alpha', true);
				ImageAlphaBlending($phpThumb->gdimg_source, false);
				ImageSaveAlpha($phpThumb->gdimg_source, true);
				$new_background_color = phpthumb_functions::ImageHexColorAllocate(
				    $phpThumb->gdimg_source, 'FFFFFF', false, 127
                );
				ImageFilledRectangle(
				    $phpThumb->gdimg_source,
                    0,
                    0,
                    $phpThumb->w,
                    $phpThumb->h,
                    $new_background_color
                );
			}
		} else {
			$phpThumb->setParameter('src', '/'.$p);
			if( $w && $h  ) {
                $phpThumb->setParameter('zc', $isLefttop ? 2 : 1);
            } else {
                $phpThumb->setParameter('aoe', 1);
            }
		}

		$phpThumb->cache_filename = null;
		$phpThumb->CalculateThumbnailDimensions();
		$phpThumb->SetCacheFilename();
		if (is_file($phpThumb->cache_filename)) {
			$nModified = filemtime($phpThumb->cache_filename);
			if ($_SERVER['REQUEST_TIME'] - $nModified < NP_TRIMIMAGE_CACHE_MAXAGE) {
				global $CONF;
				preg_match(
				    '/^'.preg_quote($this->phpThumbParams['config_document_root'], '/').'(.*)$/',
                    $phpThumb->cache_filename,
                    $matches
                );
				$fileUrl = $CONF['MediaURL'].$matches[1];
				if ($cacheCheckOnly) {
                    return $fileUrl;
                }

				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT');
				if (serverVar('HTTP_IF_MODIFIED_SINCE')
					&& ($nModified == strtotime(serverVar('HTTP_IF_MODIFIED_SINCE'))) 
					&& @ serverVar('SERVER_PROTOCOL')
				) {
					header(serverVar('SERVER_PROTOCOL').' 304 Not Modified');
					exit;
				}
				if ($getimagesize = @ GetImageSize($phpThumb->cache_filename)) {
					header('Content-Type: '.phpthumb_functions :: ImageTypeToMIMEtype($getimagesize[2]));
				} elseif (preg_match('@\.ico$@i', $phpThumb->cache_filename)) {
					header('Content-Type: image/x-icon');
				}
				
				if ($this->phpThumbParams['config_cache_force_passthru']) {
					@ readfile($phpThumb->cache_filename);
					exit;
				}
                header('Location: '.$fileUrl);
                exit;
			}
		}
		if ($cacheCheckOnly) {
			unset ($phpThumb);
			return false;
		}

		$phpThumb->GenerateThumbnail();

		if (!random_int(0, 20)) {
            $phpThumb->CleanUpCacheDirectory();
        }
		$phpThumb->RenderToFile($phpThumb->cache_filename);
		@ chmod($phpThumb->cache_filename, 0666);

		$phpThumb->OutputThumbnail();
		exit;
	}

	function canEdit() {
		global $member;
		if (!$member->isLoggedIn()) {
            return 0;
        }
		return $member->isAdmin();
	}
}
