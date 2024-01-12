<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.PeopleSearch
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\Finder\PeopleSearch\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Component\Weblinks\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;


/**
 * Allows indexing of certain People modules.
 */
final class PeopleSearch extends Adapter
{
    use DatabaseAwareTrait;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * The plugin identifier.
     *
     * @var    string
     * @since  2.5
     */
    protected $context = 'PeopleSearch';

    /**
     * The extension name.
     *
     * @var    string
     * @since  2.5
     */
    protected $extension = 'com_people';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     * @since  2.5
     */
    #protected $layout = 'weblink';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     * @since  2.5
     */
    protected $type_title = 'PeopleSearch';

    /**
     * The table name.
     *
     * @var    string
     * @since  2.5
     */
    #protected $table = '#__people';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor
     *
     * @param   DispatcherInterface  $dispatcher
     * @param   array                $config
     * @param   DatabaseInterface    $database
     */
    public function __construct(DispatcherInterface $dispatcher, array $config, DatabaseInterface $database)
    {
        self::$enabled = true;

        parent::__construct($dispatcher, $config);

        $this->setDatabase($database);
    }

    /**
     * Method to get a list of content items to index.
     *
     * @param   integer         $offset  The list offset.
     * @param   integer         $limit   The list limit.
     * @param   QueryInterface  $query   A QueryInterface object. [optional]
     *
     * @return  Result[]  An array of Result objects.
     *
     * @since   2.5
     * @throws  \Exception on database error.
     */
    protected function getItems($offset, $limit, $query = null)
    {
        $items = [];

        // Get the content items to index.
        $this->db->setQuery($this->getListQuery($query), $offset, $limit);
        $rows = $this->db->loadAssocList();

        // Convert the items to result objects.
        foreach ($rows as $row) {
            // Convert the item to a result object.
            $item = ArrayHelper::toObject($row, Result::class);

            // Sort out endcoding stuff:
            #$item->summary  = $this->utf8_convert($item->summary);

            // Set the item type.
            $item->type_id = $this->type_id;

            // Set the mime type.
            $item->mime = $this->mime;

            // Set the item layout.
            $item->layout = $this->layout;

            // Set the extension if present
            if (isset($row->extension)) {
                $item->extension = $row->extension;
            }

            // Create a useful summary to display:
            $item->summary = $item->title . (!empty($item->role) ? ', ' . trim(preg_replace('/\s+/', ' ', $item->role)) : '') . ': ' . $item->biography;

            $item->url    = '/people/' . $item->alias;
            $item->route  = '/people/' . $item->alias;
            $item->state  = 1;
            $item->access = 1;

            // Add the item to the stack.
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Method to index an item. The item must be a FinderIndexerResult object.
     *
     * @param   Result  $item  The item to index as an FinderIndexerResult object.
     *
     * @return  void
     *
     * @throws  \Exception on database error.
     * @since   2.5
     */
    protected function index(Result $item)
    {
        // Check if the extension is enabled
        if (ComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $item->setLanguage();
        $this->indexer->index($item);
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     */
    protected function setup()
    {
        return true;
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param   mixed  $query  A JDatabaseQuery object or null.
     *
     * @return  DatabaseQuery  A database object.
     *
     * @since   2.5
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        // Check if we can use the supplied SQL query.
        $query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true)
            ->select('a.id, a.name AS title, a.username, a.registerDate AS start_date')
            ->select('up1.profile_value AS alias')
            ->select('up2.profile_value AS biography')
            ->select('up3.profile_value AS role')
            ->from('#__users AS a')
            ->join('LEFT', '#__user_usergroup_map AS ugmap ON a.id = ugmap.user_id')
            ->join('LEFT', '#__usergroups AS ugp ON ugmap.group_id = ugp.id')
            ->join('LEFT', '#__user_profiles AS up1 ON a.id = up1.user_id AND up1.profile_key = "staffprofile.alias"')
            ->join('LEFT', '#__user_profiles AS up2 ON a.id = up2.user_id AND up2.profile_key = "staffprofile.biography"')
            ->join('LEFT', '#__user_profiles AS up3 ON a.id = up3.user_id AND up3.profile_key = "staffprofile.role"')
            ->where('ugp.title = "Staff"');

        return $query;
    }
}