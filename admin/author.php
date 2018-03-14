<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_author
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

if (!JFactory::getUser()->authorise('core.manage', 'com_author'))
{
	throw new JAccessExceptionNotallowed(JText::_('JERROR_ALERTNOAUTHOR'), 403);
}

// This component doesn't require admin form
JFactory::getApplication()->redirect('index.php?option=com_config&view=component&component=com_author');

