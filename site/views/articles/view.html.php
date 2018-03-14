<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_author
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/**
 * HTML View class for the Content component
 *
 * @since  1.5
 */
class AuthorViewArticles extends JViewCategory
{
	/**
	 * @var    array  Array of leading items for blog display
	 * @since  3.2
	 */
	protected $lead_items = array();

	/**
	 * @var    array  Array of intro (multicolumn display) items for blog display
	 * @since  3.2
	 */
	protected $intro_items = array();

	/**
	 * @var    array  Array of links in blog display
	 * @since  3.2
	 */
	protected $link_items = array();

	/**
	 * @var    integer  Number of columns in a multi column display
	 * @since  3.2
	 */
	protected $columns = 1;

	/**
	 * @var    string  The name of the extension for the category
	 * @since  3.2
	 */
	protected $extension = 'com_content';

	/**
	 * @var    string  Default title to use for page title
	 * @since  3.2
	 */
	protected $defaultPageTitle = 'JGLOBAL_ARTICLES';

	/**
	 * @var    string  The name of the view to link individual items to
	 * @since  3.2
	 */
	protected $viewName = 'article';

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 */
	public function display($tpl = null)
	{
		$this->commonCategoryDisplay();

		// Prepare the data
		// Get the metrics for the structural page layout.
		$params     = $this->params;
		$numLeading = $params->def('num_leading_articles', 1);
		$numIntro   = $params->def('num_intro_articles', 4);
		$numLinks   = $params->def('num_links', 4);
		$this->vote = JPluginHelper::isEnabled('content', 'vote');

		JPluginHelper::importPlugin('content');

		// Compute the article slugs and prepare introtext (runs content plugins).
		foreach ($this->items as $item)
		{
			$item->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;

			$item->parent_slug = $item->parent_alias ? ($item->parent_id . ':' . $item->parent_alias) : $item->parent_id;

			// No link for ROOT category
			if ($item->parent_alias === 'root')
			{
				$item->parent_slug = null;
			}

			$item->catslug = $item->category_alias ? ($item->catid . ':' . $item->category_alias) : $item->catid;
			$item->event   = new stdClass;

			$dispatcher = JEventDispatcher::getInstance();

			// Old plugins: Ensure that text property is available
			if (!isset($item->text))
			{
				$item->text = $item->introtext;
			}

			$dispatcher->trigger('onContentPrepare', array ('com_content.category', &$item, &$item->params, 0));

			// Old plugins: Use processed text as introtext
			$item->introtext = $item->text;

			$results = $dispatcher->trigger('onContentAfterTitle', array('com_content.category', &$item, &$item->params, 0));
			$item->event->afterDisplayTitle = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onContentBeforeDisplay', array('com_content.category', &$item, &$item->params, 0));
			$item->event->beforeDisplayContent = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onContentAfterDisplay', array('com_content.category', &$item, &$item->params, 0));
			$item->event->afterDisplayContent = trim(implode("\n", $results));
		}

		// Check for layout override only if this is not the active menu item
		// If it is the active menu item, then the view and category id will match
		$app     = JFactory::getApplication();
		$active  = $app->getMenu()->getActive();
		$menus   = $app->getMenu();
		$pathway = $app->getPathway();
		$title   = null;

		if ((!$active) || ((strpos($active->link, 'view=category') === false) || (strpos($active->link, '&id=' . (string) $this->category->id) === false)))
		{
			// Get the layout from the merged category params
			if ($layout = $this->category->params->get('category_layout'))
			{
				$this->setLayout($layout);
			}
		}
		// At this point, we are in a menu item, so we don't override the layout
		elseif (isset($active->query['layout']))
		{
			// We need to set the layout from the query in case this is an alternative menu item (with an alternative layout)
			$this->setLayout($active->query['layout']);
		}

		// For blog layouts, preprocess the breakdown of leading, intro and linked articles.
		// This makes it much easier for the designer to just interrogate the arrays.
		if ($params->get('layout_type') === 'blog' || $this->getLayout() === 'blog')
		{
			foreach ($this->items as $i => $item)
			{
				if ($i < $numLeading)
				{
					$this->lead_items[] = $item;
				}

				elseif ($i >= $numLeading && $i < $numLeading + $numIntro)
				{
					$this->intro_items[] = $item;
				}

				elseif ($i < $numLeading + $numIntro + $numLinks)
				{
					$this->link_items[] = $item;
				}
				else
				{
					continue;
				}
			}

			$this->columns = max(1, $params->def('num_columns', 1));

			$order = $params->def('multi_column_order', 1);

			if ($order == 0 && $this->columns > 1)
			{
				// Call order down helper
				$this->intro_items = ContentHelperQuery::orderDownColumns($this->intro_items, $this->columns);
			}
		}

		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();
		$author = $app->input->getInt('author_id');

		if ($menu
			&& $menu->component == 'com_author'
			&& isset($menu->query['view'], $menu->query['author_id'])
			&& $menu->query['view'] == 'articles'
			&& $menu->query['author_id'] == $author )
		{
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
			$title = $this->params->get('page_title', $menu->title);
		}
		else
		{
			// for some reason, we don't find menu item
			$title = '';
		}

		$id = (int) @$menu->query['id'];

		// Check for empty title and add site name if param is set
		if (empty($title))
		{
			$title = $app->get('sitename');
		}
		elseif ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}
		
		if (empty($title))
		{
			$title = $this->category->title;
		}

		$this->document->setTitle($title);

		if ($this->category->metadesc)
		{
			$this->document->setDescription($this->category->metadesc);
		}
		elseif ($this->params->get('menu-meta_description'))
		{
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->category->metakey)
		{
			$this->document->setMetadata('keywords', $this->category->metakey);
		}
		elseif ($this->params->get('menu-meta_keywords'))
		{
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots'))
		{
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}

		if (!is_object($this->category->metadata))
		{
			$this->category->metadata = new Registry($this->category->metadata);
		}

		if (($app->get('MetaAuthor') == '1') && $this->category->get('author', ''))
		{
			$this->document->setMetaData('author', $this->category->get('author', ''));
		}

		$mdata = $this->category->metadata->toArray();

		foreach ($mdata as $k => $v)
		{
			if ($v)
			{
				$this->document->setMetadata($k, $v);
			}
		}

		return parent::display($tpl);
	}

	/**
	 * Prepares the document
	 *
	 * @return  void
	 */
	protected function prepareDocument()
	{
		parent::prepareDocument();
		$menu = $this->menu;
		$id = (int) @$menu->query['id'];

		if ($menu && ($menu->query['option'] !== 'com_content' || $menu->query['view'] === 'article' || $id != $this->category->id))
		{
			$path = array(array('title' => $this->category->title, 'link' => ''));
			$category = $this->category->getParent();
			if( $category ):
			while (($menu->query['option'] !== 'com_content' || $menu->query['view'] === 'article' || $id != $category->id) && $category->id > 1)
			{
				$path[] = array('title' => $category->title, 'link' => ContentHelperRoute::getCategoryRoute($category->id));
				$category = $category->getParent();
			}
			endif;

			$path = array_reverse($path);

			foreach ($path as $item)
			{
				$this->pathway->addItem($item['title'], $item['link']);
			}
		}

		parent::addFeed();
	}
	
		/**
	 * Method with common display elements used in category list displays
	 *
	 * @return  boolean|\JException|void  Boolean false or \JException instance on error, nothing otherwise
	 *
	 * @since   3.2
	 */
	public function commonCategoryDisplay()
	{
		$app    = \JFactory::getApplication();
		$user   = \JFactory::getUser();
		$params = $app->getParams();

		// Get some data from the models
		$model       = $this->getModel();
		$paramsModel = $model->getState('params');

		$paramsModel->set('check_access_rights', 0);
		$model->setState('params', $paramsModel);

		$state       = $this->get('State');
		$category    = $this->get('Category');
		$children    = $this->get('CategoryChildren');
		$parent      = $this->get('CategoryParent');

		if ($category == false)
		{
			return \JError::raiseError(404, \JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
		}

		/* We are always at root
		if ($parent == false)
		{
			return \JError::raiseError(404, \JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
		}*/

		// Check whether category access level allows access.
		$groups = $user->getAuthorisedViewLevels();

		if (!in_array($category->access, $groups))
		{
			return \JError::raiseError(403, \JText::_('JERROR_ALERTNOAUTHOR'));
		}

		$items      = $this->get('Items');
		$pagination = $this->get('Pagination');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			\JError::raiseError(500, implode("\n", $errors));

			return false;
		}

		// Setup the category parameters.
		$cparams          = $category->getParams();
		$category->params = clone $params;
		$category->params->merge($cparams);

		$children = array($category->id => $children);

		// Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		if ($this->runPlugins)
		{
			\JPluginHelper::importPlugin('content');

			foreach ($items as $itemElement)
			{
				$itemElement = (object) $itemElement;
				$itemElement->event = new \stdClass;

				// For some plugins.
				!empty($itemElement->description) ? $itemElement->text = $itemElement->description : $itemElement->text = null;

				$dispatcher = \JEventDispatcher::getInstance();

				$dispatcher->trigger('onContentPrepare', array($this->extension . '.category', &$itemElement, &$itemElement->params, 0));

				$results = $dispatcher->trigger('onContentAfterTitle', array($this->extension . '.category', &$itemElement, &$itemElement->core_params, 0));
				$itemElement->event->afterDisplayTitle = trim(implode("\n", $results));

				$results = $dispatcher->trigger('onContentBeforeDisplay', array($this->extension . '.category', &$itemElement, &$itemElement->core_params, 0));
				$itemElement->event->beforeDisplayContent = trim(implode("\n", $results));

				$results = $dispatcher->trigger('onContentAfterDisplay', array($this->extension . '.category', &$itemElement, &$itemElement->core_params, 0));
				$itemElement->event->afterDisplayContent = trim(implode("\n", $results));

				if ($itemElement->text)
				{
					$itemElement->description = $itemElement->text;
				}
			}
		}

		$maxLevel         = $params->get('maxLevel', -1) < 0 ? PHP_INT_MAX : $params->get('maxLevel', PHP_INT_MAX);
		$this->maxLevel   = &$maxLevel;
		$this->state      = &$state;
		$this->items      = &$items;
		$this->category   = &$category;
		$this->children   = &$children;
		$this->params     = &$params;
		$this->parent     = &$parent;
		$this->pagination = &$pagination;
		$this->user       = &$user;

		// Check for layout override only if this is not the active menu item
		// If it is the active menu item, then the view and category id will match
		$active = $app->getMenu()->getActive();

		if ($active
			&& $active->component == $this->extension
			&& isset($active->query['view'], $active->query['id'])
			&& $active->query['view'] == 'category'
			&& $active->query['id'] == $this->category->id)
		{
			if (isset($active->query['layout']))
			{
				$this->setLayout($active->query['layout']);
			}
		}
		elseif ($layout = $category->params->get('category_layout'))
		{
			$this->setLayout($layout);
		}

		$this->category->tags = new \JHelperTags;
		$this->category->tags->getItemTags($this->extension . '.category', $this->category->id);
	}
}
