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
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License
  * as published by the Free Software Foundation; either version 2
  * of the License, or (at your option) any later version.
  * 
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  * 
  * You should have received a copy of the GNU General Public License
  * along with this program; if not, write to the Free Software
  * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301 USA
  * 
  * In addition, as a special exception, mamio and cles gives
  * permission to link the code of this program with those files in the PEAR
  * library that are licensed under the PHP License (or with modified versions
  * of those files that use the same license as those files), and distribute
  * linked combinations including the two. You must obey the GNU General Public
  * License in all respects for all of the code used other than those files in
  * the PEAR library that are licensed under the PHP License. If you modify
  * this file, you may extend this exception to your version of the file,
  * but you are not obligated to do so. If you do not wish to do so, delete
  * this exception statement from your version.
*/

//history
//	0.2:	$archive, $blogid and $catid suppot ($exmode=all ready)
//			echos 'no images' 
//	0.3:	add strtolower 
//			Initialize $this->exquery
//	0.5:	use createGlobalItemLink
//			sql_table support :-P
//	0.6:	parameter supports blogid and catid
//	0.7:	supports templatevar
//			supports popup() 
//	0.8:	supports gif
//	0.9:	doTemplateVar calls DB data for other PreItem Plugin
//	0.9:	change '&' to '&amp;'
//	1.1:	NP_Paint support.
//	    	Security Fix.
//	2.0: 	use phpThumb() (http://phpthumb.sourceforge.net)
// 	2.1:	update regex
//	    	add alt/title attribute
//	    	bug fix
//  2.2:	support <img /> tag. 
// 				doTemplateVar() bug fix.
// 				Add ENT_QUOTES to htmlspecialchars()
// 				Add ExtractImageMode
//  2.2.1:	update phpThumb() 1.7.7 . 
//  		bug fix	
//  2.2.2:	update enableLeftTop.patch
//  2.3.0:  add itemcat mode
//  2.4.0:  multi-byte filename fix
//			refactor listup(), exarray()
//			add $maxPerPage


define('NP_TRIMIMAGE_FORCE_PASSTHRU', true); //passthru(standard)
//define('NP_TRIMIMAGE_FORCE_PASSTHRU', false); //redirect(advanced)

define('NP_TRIMIMAGE_CACHE_MAXAGE', 86400 * 30); // 30days
define('NP_TRIMIMAGE_PREFER_IMAGEMAGICK', false);

require_once(dirname(__FILE__).'/sharedlibs/sharedlibs.php');
require_once('phpthumb/phpthumb.functions.php');
require_once('phpthumb/phpthumb.class.php');

class NP_TrimImage extends NucleusPlugin {
	function getName() {
		return 'TrimImage';
	}

	function getAuthor() {
		return 'nakahara21 + hsur';
	}

	function getURL() {
		return 'http://blog.cles.jp/np_cles/category/31/subcatid/15';
	}

	function getVersion() {
		return '2.4.2';
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
		
		list($amount, $maxPerItem) = explode('/', $amount, 2);
		if (!is_numeric($amount))      $amount = 10;
		if (!is_numeric($hsize))       $hsize = 80;
		if (!is_numeric($wsize))       $wsize = 80;
		if (!is_numeric($maxPerItem)) $maxPerItem = 0;
		$point = ($point == 'lefttop') ? true : false;
		$includeImg = ( $includeImg == 'true' ) ? true : false;
		
		$this->exquery = '';

		switch ($skinType) {
			case 'archive' :
				global $archive;
				$year = $month = $day = '';
				sscanf($archive, '%d-%d-%d', $year, $month, $day);
				if (empty ($day)) {
					$timestamp_start = mktime(0, 0, 0, $month, 1, $year);
					$timestamp_end = mktime(0, 0, 0, $month +1, 1, $year); // also works when $month==12
				} else {
					$timestamp_start = mktime(0, 0, 0, $month, $day, $year);
					$timestamp_end = mktime(0, 0, 0, $month, $day +1, $year);
				}
				$this->exquery .= ' and itime >= ' . mysqldate($timestamp_start)
									.' and itime < ' . mysqldate($timestamp_end);

				//break;
			default :
				if ($exmode == '' || $exmode == 'itemcat') {
					global $catid, $itemid;
					if ($catid)
						$this->exquery .= ' and icat = '.intval($catid);
					elseif( $exmode == 'itemcat' && $itemid )
						$this->exquery .= ' and icat = '.intval( $this->getCategoryIDFromItemID($itemid) );
					else
						$this->exquery .= ' and iblog = '.intval($b->getID());
				} elseif ($exmode == 'all') {
					// nothing
				} else {
					$spbid = $spcid = array ();
					$spid_array = explode('/', $exmode);
					foreach ($spid_array as $spid) {
						$type = substr($spid, 0, 1);
						$type_id = intval(substr($spid, 1));
						if( (!$type) || (!$type_id) ) continue;
						
						switch($type){
							case 'b':
								$spbid[] = $type_id;
								break;
							case 'c':
								$spcid[] = $type_id;
								break;
						}
					}
					if ($spbid){
						$this->exquery .= ' and iblog IN ('.implode(',', $spbid).') ';
					}
					if ($spcid) {
						$this->exquery .= ' and icat IN ('.implode(',', $spcid).') ';
					}
				}
		}

		$filelist = array ();
		$this->imglists = array ();
		$this->imgfilename = array ();
		$random = $random ? true : false;
		if (!($filelist = $this->_listup($amount, $random, $includeImg, $maxPerItem))) {
			//echo 'No images here.';
			return;
		}

		$amount = min($amount, count($filelist));
		echo '<div>';
		for ($i = 0; $i < $amount; $i ++) {
			$itemlink = $this->createGlobalItemLink($filelist[$i][1], '');
			echo '<a href="'.$itemlink.'">';

			$src = '';
			if (!$this->phpThumbParams['config_cache_force_passthru']) {
				$src = $this->createImage($filelist[$i][0], $wsize, $hsize, $point, true);
			}
			if (!$src) {
				$src = hsc($CONF['ActionURL'], ENT_QUOTES)
						.'?action=plugin&amp;name=TrimImage&amp;type=draw'.'&amp;p='
						.urlencode($filelist[$i][0]).'&amp;wsize='.$wsize.'&amp;hsize='.$hsize
						. ($point ? '&amp;pnt=lefttop' : '');
			}
			
			if($titlemode == 'item')
				$title = ($filelist[$i][4]) ? $filelist[$i][4] : $filelist[$i][2];
			else
				$title = ($filelist[$i][2]) ? $filelist[$i][2] : $filelist[$i][4];

			echo '<img src="'.$src.'"'			
				. ( $wsize ? ' width="'.$wsize.'" '  : '' )
				. ( $hsize ? ' height="'.$hsize.'" ' : '' )
				. ' alt="'.hsc($title, ENT_QUOTES)
				. '" title="'.hsc($title, ENT_QUOTES).'"/>';
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
		$query .= $this->exquery;
		$query .= ' ORDER BY itime DESC LIMIT '.intval($amount * 10);

		$res = sql_query($query);

		if (!sql_num_rows($res))
			return FALSE;

		while ($it = sql_fetch_object($res)) {
			$this->_parseItem($it, $maxPerItem, $includeImg);
			
			if (count($this->imglists) >= $amount)
				break;
		}
		sql_free_result($res);

		if ($random)
			shuffle($this->imglists);
		$this->imglists = array_slice($this->imglists, 0, $amount);
		return $this->imglists;
	}
	
	function _parseItem(&$item, $maxPerItem = 0, $includeImg = true){
		$pattern = '/(<%(image|popup|paint)\((.*?)\)%>)/s';
		if($includeImg){
			$pattern = '/(<%(image|popup|paint)\((.*?)\)%>)|(<img (.*?)>)/s';
		}
		
		if (preg_match_all($pattern, $item->body.$item->more, $matched)){
			if($maxPerItem){
				array_splice($matched[3], $maxPerItem); // nucleus images attribute
			}
			foreach( $matched[3] as $index => $imgAttribute ){
				if($imgAttribute){
					$this->_parseImageTag($imgAttribute, $item, false);
				} else {
					$this->_parseImageTag($matched[5][$index], $item, true);
				}
			}
		}
	}

	function _parseImageTag($imginfo, &$item, $isImg) {
		global $CONF;
		if ($isImg){
			if( preg_match_all('/(src|width|height|alt|title)=\"(.*?)\"/i', $imginfo, $matches) ) {
				$param = array();
				foreach( $matches[1] as $index => $type ){
					$param[$type] = $matches[2][$index];						
				}
				
				if( $param['src'] && ( strpos($param['src'], $CONF['MediaURL']) === 0 ) ){
					$imginfo = substr( $param['src'], strlen($CONF['MediaURL']) )
					. '|' . $param['width']
					. '|' . $param['height']
					. '|' . ( $param['title'] ? $param['tiltle'] : $param['alt']);
				}
			} else {
				return;
			}
		}
		
		list ($url, $w, $h, $alt, $ext) = explode("|", $imginfo, 5);
		if (!in_array(strtolower(strrchr($url, ".")), $this->fileex))
			return;
		if (in_array($url, $this->imgfilename))
			return;
		$this->imgfilename[] = $url;
		if (!strstr($url, '/')) {
			$url = $item->iauthor.'/'.$url;
		}
		$this->imglists[] = array ($url, $item->itemid, $alt, $ext, $item->title);
	}

	function doTemplateVar(& $item, $wsize = 80, $hsize = 80, $point = 0, $maxAmount = 0, $titlemode = '', $includeImg = 'true') {
		global $CONF;
		if (!is_numeric($hsize))
			$hsize = 80;
		if (!is_numeric($wsize))
			$wsize = 80;
		$point = ($point == 'lefttop') ? true : false;
		$includeImg = ( $includeImg == 'true' ) ? true : false;
		
		$filelist = array ();
		$this->imglists = array ();
		$this->imgfilename = array ();

		$q  = 'SELECT inumber as itemid, ititle as title, ibody as body, iauthor, itime, imore as more, ';
		$q .= 'icat as catid, iclosed as closed ';
		$q .= 'FROM '.sql_table('item').' WHERE inumber='.intval($item->itemid);
		$r = sql_query($q);
		$it = sql_fetch_object($r);
		$this->_parseItem($it, $maxAmount, $includeImg);

		if (!$this->imglists) {
			$img_tag = '<img src="'.hsc($CONF['ActionURL'], ENT_QUOTES).'?action=plugin&amp;name=TrimImage';
			$img_tag .= '&amp;type=draw&amp;p=non&amp;wsize='.$wsize.'&amp;hsize='.$hsize.$exq;
			$img_tag .= '" width="'.$wsize.'" height="'.$hsize.'" />';
			echo $img_tag;
		} else {
			foreach($this->imglists as $img) {
				$src = '';
				if (!$this->phpThumbParams['config_cache_force_passthru']) {
					$src = $this->createImage($img[0], $wsize, $hsize, $point, true);
				}
				if (!$src) {
					$src = hsc($CONF['ActionURL'], ENT_QUOTES).'?action=plugin&amp;name=TrimImage&amp;type=draw'.'&amp;p='. urlencode($img[0]) .'&amp;wsize='.$wsize.'&amp;hsize='.$hsize. ($point ? '&amp;pnt=lefttop' : '');
				}
				
				$title = ($img[2]) ? $img[2] : $img[4];
				if($titlemode == 'item')
					$title = ($img[4]) ? $img[4] : $img[2];
				
				echo '<img src="'.$src.'" '			
					. ( $wsize ? 'width="'.$wsize.'" '  : '' )
					. ( $hsize ? 'height="'.$hsize.'" ' : '' )
					. ' alt="'.hsc($title, ENT_QUOTES)
					. '" title="'.hsc($title, ENT_QUOTES).'" />';
			}
		}
	}

	function doAction($type) {
		$w = is_numeric(requestVar('wsize')) ? requestVar('wsize') : 80;
		$h = is_numeric(requestVar('hsize')) ? requestVar('hsize') : 80;
		$isLefttop = (requestVar('pnt') == 'lefttop') ? true : false;

		switch ($type) {
			case 'draw' :
				$this->createImage(requestVar('p'), $w, $h, $isLefttop);
				break;
			default :
				return 'No such action';
				break;
		}
	}

	function createImage($p, $w, $h, $isLefttop, $cacheCheckOnly = false) {
		$phpThumb = new phpThumb();
		foreach ($this->phpThumbParams as $paramKey => $paramValue) {
			$phpThumb->setParameter($paramKey, $paramValue);
		}

		if($h) $phpThumb->setParameter('h', intval($h));
		if($w) $phpThumb->setParameter('w', intval($w));

		if ($p == 'non') {
			$bghexcolor = 'FFFFFF';
			if ($phpThumb->gdimg_source = phpthumb_functions::ImageCreateFunction($phpThumb->w, $phpThumb->h)) {
				$phpThumb->setParameter('is_alpha', true);
				ImageAlphaBlending($phpThumb->gdimg_source, false);
				ImageSaveAlpha($phpThumb->gdimg_source, true);
				$new_background_color = phpthumb_functions::ImageHexColorAllocate($phpThumb->gdimg_source, $bghexcolor, false, 127);
				ImageFilledRectangle($phpThumb->gdimg_source, 0, 0, $phpThumb->w, $phpThumb->h, $new_background_color);
			}
		} else {
			$phpThumb->setParameter('src', '/'.$p);
			if( $w && $h  )
				$phpThumb->setParameter('zc', $isLefttop ? 2 : 1);
			else
				$phpThumb->setParameter('aoe', 1);
		}

		// getCache	
		$phpThumb->cache_filename = null;
		$phpThumb->CalculateThumbnailDimensions();
		$phpThumb->SetCacheFilename();
		if (file_exists($phpThumb->cache_filename)) {
			$nModified = filemtime($phpThumb->cache_filename);
			if (time() - $nModified < NP_TRIMIMAGE_CACHE_MAXAGE) {
				global $CONF;
				preg_match('/^'.preg_quote($this->phpThumbParams['config_document_root'], '/').'(.*)$/', $phpThumb->cache_filename, $matches);
				$fileUrl = $CONF['MediaURL'].$matches[1];
				if ($cacheCheckOnly)
					return $fileUrl;

				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT');
				if (@ serverVar('HTTP_IF_MODIFIED_SINCE') 
					&& ($nModified == strtotime(serverVar('HTTP_IF_MODIFIED_SINCE'))) 
					&& @ serverVar('SERVER_PROTOCOL')
				) {
					header(serverVar('SERVER_PROTOCOL').' 304 Not Modified');
					return true;
				}
				if ($getimagesize = @ GetImageSize($phpThumb->cache_filename)) {
					header('Content-Type: '.phpthumb_functions :: ImageTypeToMIMEtype($getimagesize[2]));
				}
				elseif (preg_match('@\.ico$@i', $phpThumb->cache_filename)) {
					header('Content-Type: image/x-icon');
				}
				if ($this->phpThumbParams['config_cache_force_passthru']) {
					@ readfile($phpThumb->cache_filename);
				} else {
					header('Location: '.$fileUrl);
				}
				return true;
			}
		}
		if ($cacheCheckOnly) {
			unset ($phpThumb);
			return false;
		}

		// generate
		$phpThumb->GenerateThumbnail();

		// putCache
		if (!rand(0, 20))
			$phpThumb->CleanUpCacheDirectory();
		$phpThumb->RenderToFile($phpThumb->cache_filename);
		@ chmod($phpThumb->cache_filename, 0666);

		// to browser
		$phpThumb->OutputThumbnail();
		unset ($phpThumb);
		return true;
	}

	function canEdit() {
		global $member, $manager;
		if (!$member->isLoggedIn())
			return 0;
		return $member->isAdmin();
	}

	function createGlobalItemLink($itemid, $extra = '') {
		global $CONF, $manager;
		$itemid = intval($itemid);
		if ($CONF['URLMode'] == 'pathinfo') {
			$link = $CONF['ItemURL'].'/item/'.$itemid;
		} else {
			$blogid = getBlogIDFromItemID($itemid);
			$b_tmp = & $manager->getBlog($blogid);
			$blogurl = $b_tmp->getURL();
			if (!$blogurl) {
				$blogurl = $CONF['IndexURL'];
			}
			if (substr($blogurl, -4) != '.php') {
				if (substr($blogurl, -1) != '/')
					$blogurl .= '/';
				$blogurl .= 'index.php';
			}
			$link = $blogurl.'?itemid='.$itemid;
		}
		return addLinkParams($link, $extra);
	}
}
