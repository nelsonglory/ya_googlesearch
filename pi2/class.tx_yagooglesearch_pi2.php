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
 * Plugin 'Google searchbox' for the 'ya_googlesearch' extension.
 *
 * @author	Roderick Braun <roderick.braun@ph-freiburg.de>
 * @package	TYPO3
 * @subpackage	tx_yagooglesearch
 */
class tx_yagooglesearch_pi2 extends tslib_pibase 
	{
	public $prefixId      = 'tx_yagooglesearch_pi2';		// Same as class name
	public $prefixIdMain  = 'tx_yagooglesearch_pi1';
	public $scriptRelPath = 'pi2/class.tx_yagooglesearch_pi2.php';	// Path to this script relative to the extension dir.
	public $extKey        = 'ya_googlesearch';	// The extension key.
	public $pi_checkCHash = true;
	
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

		// get the template
		$templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : 'EXT:'.$this->extKey.'/templates/template.html'; 

		// load template
		$this->template = $this->cObj->fileResource($templateFile);

		// decide to include external css file or not
		$this->includeCSS = isset($this->conf['includeCSS']) && (int)$this->conf['includeCSS'] == 0 ? 0 : 1;
		if ($this->includeCSS)
			{
			$cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : t3lib_extMgm::siteRelPath($this->extKey).'css/default.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<link rel="stylesheet" href="'.$cssFile.'" type="text/css" />';
			}

		// get action pageID
		$this->pidSearchpage = $this->conf['pidSearchpage'] ? $this->conf['pidSearchpage'] : $GLOBALS['TSFE']->id;

		// get searchform part
		$tmplSearchForm = $this->cObj->getSubpart($this->template,'###SEARCHFORM###');
    
		// add values to markers
		$contentArray = array();
		$contentArray['###PI_BASE###'] = $this->prefixIdMain;
		$contentArray['###ACTION_URL###'] = $this->pi_getPageLink($this->pidSearchpage);
		$contentArray['###SEARCH_LABEL###'] =  $this->pi_getLL('searchlabel');
		$contentArray['###SEARCHPHRASE###'] = htmlspecialchars($this->piVars['search'],ENT_QUOTES);
		$contentArray['###SUBMIT###'] = $this->pi_getLL('submit');

		// substitute template
		$content = $this->cObj->substituteMarkerArrayCached($tmplSearchForm,$contentArray);
	
		return $this->pi_wrapInBaseClass($content);
		}
	}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ya_googlesearch/pi2/class.tx_yagooglesearch_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ya_googlesearch/pi2/class.tx_yagooglesearch_pi2.php']);
}

?>
