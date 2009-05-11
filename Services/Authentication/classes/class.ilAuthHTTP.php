<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


include_once './Services/Authentication/classes/class.ilAuthDecorator.php';
include_once 'Auth/HTTP.php';

/** 
* Base class for ilAuth, ilAuthHTTP ....
* 
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id$
* 
*
* @ingroup ServicesAuthentication
*/
class ilAuthHTTP extends ilAuthDecorator
{
   
    /**
     * Constructor
     * 
	 * @param object ilAuthContainerDecorator
	 * @param array	further options Not used in the moment
     */
    function __construct(ilAuthContainerDecorator $container, $a_further_options = array())
    {
    	parent::__construct($container);
		
		$this->appendOption('sessionName',"_authhttp".md5(CLIENT_ID));
		$this->appendOption('sessionSharing',false);
		$this->initAuth();
		$this->initCallbacks();
		
    }
   
   
    /**
     * @see ilAuthDecorator::initAuth()
     */
    public function initAuth()
    {
		global $ilLog;
		
		$ilLog->write(__METHOD__.': Using Auth_HTTP');
		$this->setAuthObject(
			new Auth_HTTP(
				$this->getContainer(),
				$this->getOptions()
			));
		$this->setRealm(CLIENT_ID);
    }
}

?>