<?php
/**
 * Mantis MediaWiki extension.
 *
 * Mantis Bug Tracker integration
 *
 * Written by Helmut K. C. Tessarek
 *
 * https://www.mediawiki.org/wiki/Extension:Mantis
 * https://github.com/tessus/mwExtensionMantis
 *
 * This program is free software; you can redistribute it  and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

if ( !defined('MEDIAWIKI') )
{
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

$wgExtensionCredits['parserhook'][] = array(
	'path'         => __FILE__,
	'name'         => 'Mantis',
	'author'       => '[https://www.mediawiki.org/wiki/User:Tessus Helmut K. C. Tessarek]',
	'url'          => 'https://www.mediawiki.org/wiki/Extension:Mantis',
	'description'  => 'Mantis Bug Tracker integration',
	'license-name' => 'GPL-2.0+',
	'version'      => '1.6'
);

// Configuration variables
$wgMantisConf['DBserver']         = 'localhost'; // Mantis database server
$wgMantisConf['DBport']           = NULL;        // Mantis database port
$wgMantisConf['DBname']           = '';          // Mantis database name
$wgMantisConf['DBuser']           = '';
$wgMantisConf['DBpassword']       = '';
$wgMantisConf['DBprefix']         = '';          // Table prefix
$wgMantisConf['Url']              = '';          // Mantis Root Page
$wgMantisConf['MaxCacheTime']     = 60*60*0;     // How long to cache pages in seconds
$wgMantisConf['PriorityString']   = '10:none,20:low,30:normal,40:high,50:urgent,60:immediate';                           // $g_priority_enum_string
$wgMantisConf['StatusString']     = '10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,80:resolved,90:closed'; // $g_status_enum_string
$wgMantisConf['StatusColors']     = '10:fcbdbd,20:e3b7eb,30:ffcd85,40:fff494,50:c2dfff,80:d2f5b0,90:c9ccc4';             // $g_status_colors
$wgMantisConf['SeverityString']   = '10:feature,20:trivial,30:text,40:tweak,50:minor,60:major,70:crash,80:block';        // $g_severity_enum_string
$wgMantisConf['ResolutionString'] = '10:open,20:fixed,30:reopened,40:unable to duplicate,50:not fixable,60:duplicate,70:not a bug,80:suspended,90:wont fix'; // $g_resolution_enum_string

// create an array from a properly formatted string
function createArray( $string )
{
	$array = array();
	$entries = explode(',', $string);

	foreach ($entries as $entry)
	{
		list($key, $value) = explode(':', $entry);
		$array[$key] = $value;
	}

	return $array;
}

// get key or value from an array
function getKeyOrValue( $keyValue, $array )
{
	if (is_numeric($keyValue))
	{
		// get value from key
		if (array_key_exists($keyValue, $array))
		{
			return $array[$keyValue];
		}
		else
		{
			return false;
		}
	}
	else
	{
		// get key from value
		if (in_array($keyValue, $array))
		{
			return array_search($keyValue, $array);
		}
		else
		{
			return false;
		}
	}
}

$wgHooks['ParserFirstCallInit'][] = 'wfMantis';

function wfMantis( &$parser )
{
	$parser->setHook('mantis', 'renderMantis');
	return true;
}

// check an array against records in a table.
// only return values from that array which also exist in the database
function intersectArrays( $dbcontext, $prefix, $table, $column, $checkArray )
{
	$databaseRecords = array();
	$newArray = array();
	$dbQuery = "select $column from ${prefix}$table";
	if ($result = ${dbcontext}->query($dbQuery))
	{
		while ($row = $result->fetch_assoc())
		{
			$databaseRecords[] = $row[$column];
		}
		$result->close();
	}
	$items = explode(',', $checkArray);
	foreach ($items as $item)
	{
		$item = trim($item);
		if (in_array($item, $databaseRecords))
		{
			$newArray[] = $item;
		}
	}
	return $newArray;
}

// The callback function for converting the input text to HTML output
function renderMantis( $input, $args, $mwParser )
{
	global $wgMantisConf;

	if ($wgMantisConf['MaxCacheTime'] !== false)
	{
		$mwParser->getOutput()->updateCacheExpiry($wgMantisConf['MaxCacheTime']);
	}

	$columnNames = 'id:b.id,project:p.name,category:c.name,severity:b.severity,priority:b.priority,status:b.status,username:u.username,created:b.date_submitted,updated:b.last_updated,summary:b.summary,fixed_in_version:b.fixed_in_version,version:b.version,target_version:b.target_version,resolution:b.resolution';

	$conf['bugid']            = NULL;
	$conf['table']            = 'sortable';
	$conf['header']           = true;
	$conf['color']            = true;
	$conf['status']           = 'open';
	$conf['severity']         = NULL;
	$conf['count']            = NULL;
	$conf['orderby']          = 'b.last_updated';
	$conf['order']            = 'desc';
	$conf['dateformat']       = 'Y-m-d';
	$conf['suppresserrors']   = false;
	$conf['suppressinfo']     = false;
	$conf['summarylength']    = NULL;
	$conf['project']          = NULL;
	$conf['category']         = NULL;
	$conf['show']             = array('id','category','severity','status','updated','summary');
	$conf['comment']          = NULL;
	$conf['fixed_in_version'] = NULL;
	$conf['version']          = NULL;
	$conf['target_version']   = NULL;
	$conf['username']         = NULL;
	$conf['resolution']       = NULL;

	$tableOptions   = array('sortable', 'standard', 'noborder');
	$orderbyOptions = createArray($columnNames);

	$mantis['status']     = createArray($wgMantisConf['StatusString']);
	$mantis['color']      = createArray($wgMantisConf['StatusColors']);
	$mantis['priority']   = createArray($wgMantisConf['PriorityString']);
	$mantis['severity']   = createArray($wgMantisConf['SeverityString']);
	$mantis['resolution'] = createArray($wgMantisConf['ResolutionString']);

	$view = "view.php?id=";

	$parameters = explode("\n", $input);

	foreach ($parameters as $parameter)
	{
		$paramField = explode('=', $parameter, 2);
		if (count($paramField) < 2)
		{
			continue;
		}
		$type  = strtolower(trim($paramField[0]));
		$csArg = trim($paramField[1]);
		$arg   = strtolower(trim($paramField[1]));
		switch ($type)
		{
			case 'bugid':
				$bugid = array();
				$bugids = explode(',', $arg);
				foreach ($bugids as $bug)
				{
					if (is_numeric($bug))
					{
						$bugid[] = intval($bug);
					}
				}
				if (!empty($bugid))
				{
					$conf['bugid'] = $bugid;
					if (count($bugid) == 1)
					{
						$conf['color']  = false;
						$conf['header'] = false;
					}
				}
				break;
			case 'status':
				if (((in_array($arg, $mantis['status'])) !== FALSE ) || $arg == 'open' || $arg == 'all')
				{
					if ($arg != 'all')
					{
						$conf['status'] = $arg;
					}
					else
					{
						$conf['status'] = NULL;
					}
				}
				break;
			case 'severity':
				if ((in_array($arg, $mantis['severity'])) !== FALSE )
				{
					$conf['severity'] = $arg;
				}
				break;
			case 'table':
				if ((in_array($arg, $tableOptions)) !== FALSE )
				{
					$conf['table'] = $arg;
				}
				break;
			case 'count':
			case 'summarylength':
				if (is_numeric($arg) && ($arg > 0))
				{
					$conf["$type"] = intval($arg);
				}
				break;
			case 'order':
				if ($arg == 'asc' || $arg == 'ascending')
				{
					$conf['order'] = 'asc';
				}
				else
				{
					$conf['order'] = 'desc';
				}
				break;
			case 'orderby':
			case 'sortkey':
			case 'ordermethod':
				if (array_key_exists($arg, $orderbyOptions))
				{
					$conf['orderby'] = $orderbyOptions[$arg];
				}
				break;
			case 'suppresserrors':
			case 'suppressinfo':
			case 'color':
			case 'header':
				if ($arg == 'true' || $arg == 'yes' || $arg == 'on')
				{
					$conf["$type"] = true;
				}
				elseif ($arg == 'false' || $arg == 'no' || $arg == 'off')
				{
					$conf["$type"] = false;
				}
				break;
			case 'dateformat':
				$conf['dateformat'] = $arg;
				break;
			case 'show':
				$showNew = array();
				$columns = explode(',', $arg);
				foreach ($columns as $column)
				{
					$column = trim($column);
					if (array_key_exists($column, $orderbyOptions))
					{
						$showNew[] = $column;
					}
				}
				if (!empty($showNew))
				{
					$conf['show'] = $showNew;
				}
				break;
			case 'resolution':
				$resNew = array();
				$columns = explode(',', $arg);
				foreach ($columns as $column)
				{
					$column = trim($column);
					if ((in_array($column, $mantis['resolution'])) !== FALSE)
					{
						$resNew[] = $column;
					}
				}
				if (!empty($resNew))
				{
					$conf['resolution'] = $resNew;
				}
				break;
			case 'project':
				$tmpProjects = $csArg;
				break;
			case 'category':
				$tmpCategories = $csArg;
				break;
			case 'fixed_in_version':
			case 'fixed_in':
				$tmpFixedInVersions = $csArg;
				break;
			case 'version':
				$tmpVersions = $csArg;
				break;
			case 'target_version':
			case 'target':
				$tmpTargetVersions = $csArg;
				break;
			case 'username':
				$tmpUsernames = $csArg;
				break;
			default:
				break;
		} // end main switch()
		if (substr($type, 0, 7) == "comment")
		{
			if (is_numeric(substr($type, 8)))
			{
				$id = intval(substr($type, 8));
				$conf['comment'][$id] = $csArg;
			}
		}
	} // end foreach()

	// build the link url
	$link = NULL;

	if (!empty($wgMantisConf['Url']))
	{
		if (substr($wgMantisConf['Url'], -1) == '/')
		{
			$link = $wgMantisConf['Url'].$view;
		}
		else
		{
			$link = $wgMantisConf['Url'].'/'.$view;
		}
	}

	$tabprefix = $wgMantisConf['DBprefix'];

	// connect to mantis database
	$db = new mysqli($wgMantisConf['DBserver'], $wgMantisConf['DBuser'], $wgMantisConf['DBpassword'], $wgMantisConf['DBname'], $wgMantisConf['DBport']);

	/* check connection */
	if ($db->connect_errno)
	{
		$errmsg = sprintf("Connect to [%s] failed: %s\n", $wgMantisConf['DBname'], $db->connect_error);
		if ($conf['suppresserrors'])
		{
			$errmsg = '';
		}
		return $errmsg;
	}

	$db->set_charset("utf8");

	// create project array - accept only project names that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpProjects))
	{
		$projectNew = intersectArrays($db, $tabprefix, 'project_table', 'name', $tmpProjects);
		if (!empty($projectNew))
		{
			$conf['project'] = $projectNew;
		}
	}

	// create category array - accept only category names that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpCategories))
	{
		$categoryNew = intersectArrays($db, $tabprefix, 'category_table', 'name', $tmpCategories);
		if (!empty($categoryNew))
		{
			$conf['category'] = $categoryNew;
		}
	}

	// create fixed_in_version array - accept only versions that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpFixedInVersions))
	{
		$versionNew = intersectArrays($db, $tabprefix, 'project_version_table', 'version', $tmpFixedInVersions);
		if (!empty($versionNew))
		{
			$conf['fixed_in_version'] = $versionNew;
		}
	}

	// create version array - accept only versions that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpVersions))
	{
		$versionNew = intersectArrays($db, $tabprefix, 'project_version_table', 'version', $tmpVersions);
		if (!empty($versionNew))
		{
			$conf['version'] = $versionNew;
		}
	}

	// create target_version array - accept only versions that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpTargetVersions))
	{
		$versionNew = intersectArrays($db, $tabprefix, 'project_version_table', 'version', $tmpTargetVersions);
		if (!empty($versionNew))
		{
			$conf['target_version'] = $versionNew;
		}
	}

	// create username array - accept only usernames that exist in the database to prevent SQL injection
	// this check decreases performance a tiny bit, because we have to make another db call. but security comes first!
	if (!empty($tmpUsernames))
	{
		$userNew = intersectArrays($db, $tabprefix, 'user_table', 'username', $tmpUsernames);
		if (!empty($userNew))
		{
			$conf['username'] = $userNew;
		}
	}

	// build the SQL query
	$query = "select
		b.id as id,
		p.name as project,
		c.name as category,
		b.severity as severity,
		b.priority as priority,
		b.status as status,
		u.username as username,
		b.date_submitted as created,
		b.last_updated as updated,
		b.summary as summary,
		b.fixed_in_version as fixed_in_version,
		b.version as version,
		b.target_version as target_version,
		b.resolution as resolution
		from
		${tabprefix}category_table c
		inner join ${tabprefix}bug_table b on (b.category_id = c.id)
		inner join ${tabprefix}project_table p on (b.project_id = p.id)
		left outer join ${tabprefix}user_table u on (u.id = b.handler_id) ";

	if ($conf['bugid'] == NULL)
	{
		if ($conf['status'])
		{
			if ($conf['status'] == 'open')
			{
				$status = getKeyOrValue('closed', $mantis['status']);
				$cond = "<> $status";
			}
			else
			{
				$status = getKeyOrValue($conf['status'], $mantis['status']);
				$cond = "= $status";
			}
			$query .= "where b.status $cond ";
		}
		else
		{
			$query .= "where 1=1 ";
		}

		if ($conf['severity'])
		{
			$severity = getKeyOrValue($conf['severity'], $mantis['severity']);
			$query .= "and b.severity = $severity ";
		}

		if ($conf['resolution'])
		{
			$resolutionNumbers = array();
			// get the numerical values for the resolution names
			foreach ($conf['resolution'] as $res)
			{
				$resolutionNumbers[] = getKeyOrValue($res, $mantis['resolution']);
			}
			$inlist = implode(",", $resolutionNumbers);
			$query .= "and b.resolution in ( $inlist ) ";
		}

		if ($conf['project'])
		{
			$inlist = "'".implode("','", $conf['project'])."'";
			$query .= "and p.name in ( $inlist ) ";
		}

		if ($conf['category'])
		{
			$inlist = "'".implode("','", $conf['category'])."'";
			$query .= "and c.name in ( $inlist ) ";
		}

		if ($conf['fixed_in_version'])
		{
			$inlist = "'".implode("','", $conf['fixed_in_version'])."'";
			$query .= "and b.fixed_in_version in ( $inlist ) ";
		}

		if ($conf['version'])
		{
			$inlist = "'".implode("','", $conf['version'])."'";
			$query .= "and b.version in ( $inlist ) ";
		}

		if ($conf['target_version'])
		{
			$inlist = "'".implode("','", $conf['target_version'])."'";
			$query .= "and b.target_version in ( $inlist ) ";
		}

		if ($conf['username'])
		{
			$inlist = "'".implode("','", $conf['username'])."'";
			$query .= "and u.username in ( $inlist ) ";
		}

		$query .= "order by ${conf['orderby']} ${conf['order']} ";

		if (($conf['count'] != NULL) && $conf['count'] > 0)
		{
			$query .= "limit ${conf['count']}";
		}
	}
	else
	{
		// I'm a performance guy, so I differentiate between a single row access and an IN list
		// who knows how stupid the database engine is
		if (count($conf['bugid']) == 1)
		{
			$id = $conf['bugid'][0];
			$query .= "where b.id = $id";
		}
		else
		{
			$inlist = implode(',', $conf['bugid']);
			$query .= "where b.id in ( $inlist ) ";
			$query .= "order by ${conf['orderby']} ${conf['order']} ";
			if (($conf['count'] != NULL) && $conf['count'] > 0)
			{
				$query .= "limit ${conf['count']}";
			}
		}
	}
	if ($result = $db->query($query))
	{
		// check if there are any rows in resultset
		if ($result->num_rows == 0)
		{
			if ($conf['bugid'])
			{
				// only one bugid specified
				if (count($conf['bugid']) == 1)
				{
					$errmsg = sprintf("No MANTIS entry (%07d) found.\n", $conf['bugid'][0]);
				}
				// a list of bugs specified
				else
				{
					$errmsg = sprintf("No MANTIS entries found.\n");
				}
			}
			else
			{
				$useAnd = false;
				$errmsg = "No MANTIS entries with ";

				if ($conf['status'])
				{
					$errmsg .= sprintf("status '%s'", $conf['status']);
					$useAnd = true;
				}

				if ($conf['severity'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("severity '%s'", $conf['severity']);
					$useAnd = true;
				}

				if ($conf['category'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("category '%s'", implode("'", $conf['category']));
					$useAnd = true;
				}

				if ($conf['project'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("project '%s'", implode("'", $conf['project']));
					$useAnd = true;
				}

				if ($conf['fixed_in_version'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("fixed_in_version '%s'", implode("'", $conf['fixed_in_version']));
					$useAnd = true;
				}

				if ($conf['version'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("version '%s'", implode("'", $conf['version']));
					$useAnd = true;
				}

				if ($conf['target_version'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("target_version '%s'", implode("'", $conf['target_version']));
					$useAnd = true;
				}

				if ($conf['username'])
				{
					if ($useAnd)
					{
						$errmsg .= " and ";
					}
					$errmsg .= sprintf("username '%s'", implode("'", $conf['username']));
					$useAnd = true;
				}

				$errmsg .= " found.\n";

				if (!$useAnd)
				{
					$errmsg = sprintf("No MANTIS entries found.\n");
				}
			}
			$result->free();
			$db->close();
			if ($conf['suppressinfo'])
			{
				$errmsg = '';
			}
			return $errmsg;
		}

		// create table start
		$output = '{| class="wikitable sortable"'."\n";

		// create table header - use an array to specify which columns to display
		if ($conf['header'])
		{
			foreach ($conf['show'] as $colname)
			{
				$output .= "!".ucfirst($colname)."\n";
			}
			if (!empty($conf['comment']))
			{
				$output .= "!Comment\n";
			}
		}

		$format = "|style=\"padding-left:10px; padding-right:10px; color: black; background-color: #%s; text-align:%s\" |";

		// create table rows
		while ($row = $result->fetch_assoc())
		{
			$output .= "|-\n";

			foreach ($conf['show'] as $colname)
			{
				if ($conf['color'])
				{
					$color = $mantis['color'][$row['status']];
				}
				else
				{
					$color = "f9f9f9";
				}

				switch ($colname)
				{
					case 'id':
						$output .= sprintf($format, $color, 'center');
						if ($link)
						{
							$output .= sprintf("[%s%d %07d]\n", $link, $row[$colname], $row[$colname]);
						}
						else
						{
							$output .= sprintf("%07d\n", $row[$colname]);
						}
						break;
					case 'severity':
					case 'priority':
					case 'resolution':
						$output .= sprintf($format, $color, 'center');
						$output .= getKeyOrValue($row[$colname], $mantis[$colname])."\n";
						break;
					case 'status':
						$output .= sprintf($format, $color, 'center');
						$assigned = '';
						if ($username = $row['username'])
						{
							$assigned = "(${username})";
						}
						$output .= sprintf("%s %s\n", getKeyOrValue($row[$colname], $mantis[$colname]), $assigned);
						break;
					case 'summary':
						$output .= sprintf($format, $color, 'left');
						$summary = $row[$colname];
						if ($conf['summarylength'] && (strlen($summary) > $conf['summarylength']))
						{
							$summary = trim(substr($row[$colname], 0, $conf['summarylength']))."...";
						}
						$output .= $summary."\n";
						break;
					case 'updated':
					case 'created':
						$output .= sprintf($format, $color, 'left');
						$output .= date($conf['dateformat'], $row[$colname])."\n";
						break;
					default:
						$output .= sprintf($format, $color, 'center');
						$output .= $row[$colname]."\n";
						break;
				}
			}
			if (!empty($conf['comment']))
			{
				$output .= sprintf($format, $color, 'left');

				if (array_key_exists($row[id], $conf['comment']))
				{
					$output .= $conf['comment'][$row[id]]."\n";
				}
				else
				{
					$output .= "\n";
				}
			}
		}
		// create table end
		$output .= "|}\n";

		$result->free();
	}
	else
	{
		if ($conf['suppresserrors'])
		{
			return '';
		}
		else
		{
			return "Query failed! Check database settings and table prefix! (Missing '_' ?)\n";
		}
	}

	$db->close();

	//wfMessage("Test Message")->plain();
	return $mwParser->recursiveTagParse($output);
}
?>
