<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Menu
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_PLATFORM') or die;

/**
 * JMenu class
 *
 * @since  1.5
 */
class JMenuSite extends JMenu
{
	/**
	 * Loads the entire menu table into memory.
	 *
	 * @return  boolean  True on success, false on failure
	 *
	 * @since   1.5
	 */
	public function load()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('m.id, m.menutype, m.title, m.alias, m.note, m.path AS route, m.link, m.type, m.level, m.language')
			->select($db->quoteName('m.browserNav') . ', m.access, m.params, m.home, m.img, m.template_style_id, m.component_id, m.parent_id')
			->select('e.element as component')
			->from('#__menu AS m')
			->join('LEFT', '#__extensions AS e ON m.component_id = e.extension_id')
			->where('m.published = 1')
			->where('m.parent_id > 0')
			->where('m.client_id = 0')
			->order('m.lft');

		// Set the query
		$db->setQuery($query);

		try
		{
			$this->_items = $db->loadObjectList('id');
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, JText::sprintf('JERROR_LOADING_MENUS', $e->getMessage()));

			return false;
		}

		foreach ($this->_items as &$item)
		{
			// Get parent information.
			$parent_tree = array();

			if (isset($this->_items[$item->parent_id]))
			{
				$parent_tree  = $this->_items[$item->parent_id]->tree;
			}

			// Create tree.
			$parent_tree[] = $item->id;
			$item->tree = $parent_tree;

			// Create the query array.
			$url = str_replace('index.php?', '', $item->link);
			$url = str_replace('&amp;', '&', $url);

			parse_str($url, $item->query);
		}

		return true;
	}

	/**
	 * Gets menu items by attribute
	 *
	 * @param   string   $attributes  The field name
	 * @param   string   $values      The value of the field
	 * @param   boolean  $firstonly   If true, only returns the first item found
	 *
	 * @return  array
	 *
	 * @since   1.6
	 */
	public function getItems($attributes, $values, $firstonly = false)
	{
		$attributes = (array) $attributes;
		$values     = (array) $values;
		$app        = JApplication::getInstance('site');

		if ($app->isSite())
		{
			// Filter by language if not set
			if (($key = array_search('language', $attributes)) === false)
			{
				if (JLanguageMultilang::isEnabled())
				{
					$attributes[] 	= 'language';
					$values[] 		= array(JFactory::getLanguage()->getTag(), '*');
				}
			}
			elseif ($values[$key] === null)
			{
				unset($attributes[$key]);
				unset($values[$key]);
			}

			// Filter by access level if not set
			if (($key = array_search('access', $attributes)) === false)
			{
				$attributes[] = 'access';
				$values[] = JFactory::getUser()->getAuthorisedViewLevels();
			}
			elseif ($values[$key] === null)
			{
				unset($attributes[$key]);
				unset($values[$key]);
			}
		}

		// Reset arrays or we get a notice if some values were unset
		$attributes = array_values($attributes);
		$values = array_values($values);

		return parent::getItems($attributes, $values, $firstonly);
	}

	/**
	 * Get menu item by id
	 *
	 * @param   string  $language  The language code.
	 *
	 * @return  mixed  The item object or null when not found for given language
	 *
	 * @since   1.6
	 */
	public function getDefault($language = '*')
	{
		if (array_key_exists($language, $this->_default) && JApplication::getInstance('site')->getLanguageFilter())
		{
			return $this->_items[$this->_default[$language]];
		}
		elseif (array_key_exists('*', $this->_default))
		{
			return $this->_items[$this->_default['*']];
		}
		else
		{
			return null;
		}
	}
}
