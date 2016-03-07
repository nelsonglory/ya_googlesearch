<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Roderick Braun <roderick.braun@ph-freiburg.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Plugin 'Google search' for the 'ya_googlesearch' extension.
 *
 * @author	Roderick Braun <roderick.braun@ph-freiburg.de>
 * @package	TYPO3
 * @subpackage	tx_yagooglesearch
 */
class tx_yagooglesearch_pi1 extends tslib_pibase 
	{
	public $prefixId      = 'tx_yagooglesearch_pi1';		// Same as class name
	public $scriptRelPath = 'pi1/class.tx_yagooglesearch_pi1.php';	// Path to this script relative to the extension dir.
	public $extKey        = 'ya_googlesearch';			// The extension key.
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */

	public function main($content,$conf)	
		{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		// disable caching
		$this->pi_USER_INT_obj=1;
		
		# define picturePath
		$this->picturePath = $this->conf['picturePath'] ? $this->conf['picturePath'].'/' : t3lib_extMgm::extRelPath($this->extKey).'images/';

		// get the template
		$templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : 'EXT:'.$this->extKey.'/templates/template.html'; 

		// load template
		$this->template = $this->cObj->fileResource($templateFile);
		
		// decide to include external css file or not
		if (!(isset($this->conf['includeCSS']) && (int)$this->conf['includeCSS'] == 0))
			{
			$cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : t3lib_extMgm::siteRelPath($this->extKey).'css/default.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<link rel="stylesheet" href="'.$cssFile.'" type="text/css" />';
			}

		// should search be restricted to domain ?
		$this->domainRest = $this->conf['domainRestriction'] ? 'site:'.$this->conf['domainRestriction'] : '';

		// restrict search to terms in URL
                $this->inURL = $this->conf['inURL'] ? 'inurl:'.$this->conf['inURL'] : '';

		// max length of displayed url
		$this->maxURLlength = $this->conf['maxURLlength'] ? (int)$this->conf['maxURLlength'] : 60;

		// preferred language in search results
		$this->preferredLanguage = $this->conf['preferredLanguage'] ? $this->conf['preferredLanguage'] : 'en';

		// Character encoding
		$this->charEncoding = $this->conf['charEncoding'] ? $this->conf['charEncoding'] : 'UTF-8';

		// Show the google cached url
		$this->showGoogleCached = (int)$this->conf['showGoogleCached'] ? 1 : 0;

		// Base URL of the google-service
		$this->googleBaseURL = 'http://ajax.googleapis.com/ajax/services/search/web?v=1.0&rsz=large&hl='.$this->preferredLanguage;

		// get searchform part
		$tmplSearchForm = $this->cObj->getSubpart($this->template,'###SEARCHFORM###');
    
		// add values to markers
		$contentArray = array();
		$contentArray['###PI_BASE###'] = $this->prefixId;
		$contentArray['###ACTION_URL###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
		$contentArray['###SEARCHPHRASE###'] = htmlspecialchars($this->piVars['search'],ENT_QUOTES); 
		$contentArray['###SUBMIT###'] = $this->pi_getLL('submit');

		// substitute template
		$content = $this->cObj->substituteMarkerArrayCached($tmplSearchForm,$contentArray);

		if ($this->piVars['search'])
			{
			$pageIndex = (int)$this->piVars['page']>0 ? $this->piVars['page']*8 : (int)$this->piVars['start'];
			$searchURL = $this->deriveURL($this->piVars['search'],$pageIndex);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $searchURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_REFERER, $_SERVER['SERVER_NAME']);
			$result = curl_exec($ch);
			curl_close($ch);

			// now, process the JSON string
			$json = json_decode($result);

			// some vars for better handling
			$resultArr = $json->responseData->results;
			$pageArr = $json->responseData->cursor->pages;
			$nrPages = count($pageArr);
			$estCount = $json->responseData->cursor->estimatedResultCount;
	
			// set 'real'pageIndex
			$pageIndex = $json->responseData->cursor->currentPageIndex*8;

			if ($resultArr)
				{
				// get template subparts SEARCHRESULT & RESULTLIST
				$tmplSearchResult = $this->cObj->getSubpart($this->template,'###SEARCHRESULT###');
				$tmplResultList = $this->cObj->getSubpart($this->template,'###RESULTLIST###');

				$resultInfo = $this->pi_getLL('results').' <b>'.($pageIndex+1).' - '.(($nrPages > 1) ? ($pageIndex+8 > $estCount ? $estCount : $pageIndex+8) : count($resultArr)).'</b>';
				$resultInfo .= ($estCount > 8) ? ' '.$this->pi_getLL('of_approx').' <b>'.$estCount.'</b>' : '';
				$searchResults['###RESULTINFO###'] = $this->wrapInHtmlTag($resultInfo,'resultInfo');
				$searchResults['###BRANDING###'] = $this->wrapInHtmlTag('[ powered by '.$this->cObj->typolink('<img src="'.$this->picturePath.'google.png" align="top" title="Google" alt="Google" />',array('parameter' => 'www.google.com', 'extTarget' => '_blank')).']','branding');

				// create pagebrowser
				if ($nrPages > 1)
					{
					// use pagebrowse plugin if possible
					$searchResults['###PAGEBROWSER###'] = t3lib_extMgm::isLoaded('pagebrowse') ?
					$this->getPagebrowsePluginObj($nrPages,array($this->prefixId => array('search' => $this->piVars['search']),'no_cache' => 1)) :
					$this->getDefaultPagebrowseObj($nrPages,$pageArr,$pageIndex);
					} else $searchResults['###PAGEBROWSER###'] = '';

				// display results	
				foreach($resultArr as $item)
					{
					// delete previous content
					$contentArray=array();
					// convert UTF-8 encoded result to charEncoding
					foreach ($item as $key => $value) $item->$key = iconv('UTF-8',$this->charEncoding.'//IGNORE',$value);
					$contentArray['###TITLE###'] = $this->wrapInHtmlTag($this->cObj->typolink($item->title,array('parameter' => urldecode($item->url))),'title');
					$contentArray['###CONTENT###'] = $this->wrapInHtmlTag($item->content, 'content');
					// decide to create a link to google cache or not
					$cached = ($this->showGoogleCached && $item->cacheUrl) ?
						$this->cObj->typolink('[Google Cache]',array('parameter' => urldecode($item->cacheUrl))) : '';
					$contentArray['###URL###'] = $this->wrapInHtmlTag($this->shortURL(urldecode($item->unescapedUrl)).' '.$cached, 'url');
					$searchResults['###SEARCHRESULTS###'] .= $this->wrapInHtmlTag($this->cObj->substituteMarkerArrayCached($tmplSearchResult,$contentArray),'searchContent');
					}
				} else $content .= $this->wrapInHtmlTag($this->pi_getLL('no_result'),'noResults');
			$content .= $this->cObj->substituteMarkerArrayCached($tmplResultList,$searchResults);
			} 
		return $this->pi_wrapInBaseClass($content);
		}

	private function deriveURL($searchTerm,$index=0)
		{
		$query = urlencode(iconv($this->charEncoding,'UTF-8//IGNORE',$searchTerm).' '.$this->domainRest.' '.$this->inURL.' -'.$this->prefixId);
		return $this->googleBaseURL.'&start='.$index.'&q='.$query;
		}

	private function shortURL($longURL)
		{
		// first strip http(s) 
		$shortURL = preg_replace('/^https?:\/\//','',$longURL);

		if (strlen($shortURL) > $this->maxURLlength)
			{
			// get target
			preg_match('/^(.+)\/([^\/]+)$/',$shortURL,$matches);
			
			// calc left part of $longURL
			$urlLeftLength = $this->maxURLlength - strlen($matches[2]);
			if ($urlLeftLength > 0)
				{
				$strLeft = substr($shortURL,0,$urlLeftLength);
				$shortURL = $strLeft.'...'.$matches[2];
				} else $shortURL = '...'.substr($matches[2],0,$this->maxURLlength);
			}
		return $shortURL;
		}

	private function wrapInHtmlTag($str,$class)
		{
		if (!(isset($this->conf[$class.'.']['applyWrap']) && (int)$this->conf[$class.'.']['applyWrap'] == 0))
			{
			$htmlTag = isset($this->conf[$class.'.']['wrapHtmlTag']) ? trim($this->conf[$class.'.']['wrapHtmlTag']) : 'div';
			$cssClass = (isset($this->conf[$class.'.']['attachCssClass']) && (int)$this->conf[$class.'.']['attachCssClass'] == 0) ? '' : $this->pi_classParam($class); 
			$str = $str ? '<'.$htmlTag.$cssClass.'>'.$str.'</'.$htmlTag.'>' : '';
			}
		return $str;
		}
	
	private function getDefaultPagebrowseObj($nrPages,$pages,$currPageIndex)
		{
		$pageNr = (int)($currPageIndex/8);

		foreach($pages as $page)
			{
			$pageLink = ($pageNr == $page->label-1) ? 
			'<b>'.$page->label.'</b>' : $this->pi_linkTP_keepPIvars($page->label,array('search' => $this->piVars['search'], 'start' => $page->start),0,1);
			$pageLinks .= ' '.$pageLink.' ';
			}
		// add forward / backward links
		if ($currPageIndex != 0) 
			$pageLinks = $this->pi_linkTP_keepPIvars('<img src="'.$this->picturePath.'backward.png" align="top" title="'.$this->pi_getLL('backward').'" alt="['.$this->pi_getLL('backward').']" />',array('search' => $this->piVars['search'], 'start' => $pages[$pageNr-1]->start),0,1).$pageLinks;
		if ($nrPages > 1 && $pageNr < $nrPages-1)
			$pageLinks = $pageLinks.$this->pi_linkTP_keepPIvars('<img src="'.$this->picturePath.'forward.png" align="top" title="'.$this->pi_getLL('forward').'" alt="['.$this->pi_getLL('forward').']" />',array('search' => $this->piVars['search'], 'start' => $pages[$pageNr+1]->start),0,1);
		return $this->wrapInHtmlTag($pageLinks,'pageLinks');
		}

	private function getPagebrowsePluginObj($numberOfPages, $additionalParameters=array())
		{
		// Get default configuration 
		$conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'];
		// Modify this configuration
		$conf['pageParameterName'] = $this->prefixId . '|page';
		$conf['numberOfPages'] = $numberOfPages;
		if(count($additionalParameters)>0) $conf['extraQueryString'] = t3lib_div::implodeArrayForUrl(null, $additionalParameters);
		// Get page browser
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array(), '');
		return $this->wrapInHtmlTag($cObj->cObjGetSingle('USER_INT', $conf),'pageLinks');
		}
	}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ya_googlesearch/pi1/class.tx_yagooglesearch_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ya_googlesearch/pi1/class.tx_yagooglesearch_pi1.php']);
}

?>
