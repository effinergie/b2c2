<?php
defined( '_JEXEC' ) or die; //appel uniquement depuis le CMS

		
class CMSEffiUtils{
	
	static function getURLProjet(){
		return JURI::base();
	}
		

		
	static function getCMSUriBase(){
		return JURI::base();
	}
	
	static function getCMSUriProjBase(){
		$cur_dir = preg_split('#(/|\\\\)#', __dir__);
		$cur_dir = $cur_dir[count($cur_dir)-2];
		return CMSEffiUtils::getCMSUriBase().'libraries/effinergie/sourcerer/'.$cur_dir .'/';
	}	
	
	static function getCMSUserID(){
		//renvoie l'ID de l'utilisateur du CMS utilisé. 
		//renvoie Faux sinon. 
		//à adapter selon le CMS (ici Joomla)
		$user = JFactory::getUser();
		return $user->get('id');
	}
	
	static function userIsManagerB2C2(){
		$groupId = 10;
		return CMSEffiUtils::userAuthGroupID($groupId);
	}
	
	static function userAuthGroupID($groupID){
		return (array_search($groupID,JFactory::getUser()->getAuthorisedGroups()) !==false);
	}	
	
	static function userAuthView§LevelID($viewLevelID){
		return (array_search($viewLevelID,JFactory::getUser()->getAuthorisedViewLevels()) !==false);
	}
	
	static function saveCMSData($Data,$tableName){
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Insert columns.
		//$columns = array('user_id', 'profile_key', 'profile_value', 'ordering');
		$columns = array_keys($Data);

		// Insert values.
		//$values = array(1001, $db->quote('custom.message'), $db->quote('Inserting a record using insert()'), 1);
		$values = array_values($Data);
		
		

		// Prepare the insert query.
		$query
			//->insert($db->quoteName('#__user_profiles'))
			->insert($db->quoteName('#__'.$tableName))
			->columns($db->quoteName($columns))
			//->values($db->quote(implode(',', $values)));
			->values(implode(',',$db->quote($values)));
//echo $query->dump() ;die;
		// Set the query using our newly populated query object and execute it.
		$db->setQuery($query);
		if ($db->execute()){
			return $db->insertid();
		} else {
			return false;
		}
	}
	
	
	
	
	static function updateCMSData($Data,$tableName,$sCondition){
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$fields = array();
		foreach ($Data as $nomChamp=>$valChamp){
			// Fields to update.
			$fields[] = $db->quoteName($nomChamp) . ' = ' . $db->quote($valChamp);
		/*	$fields = array(
				$db->quoteName('profile_value') . ' = ' . $db->quote('Updating custom message for user 1001.'),
				$db->quoteName('ordering') . ' = 2'
			);*/
		}

		// Conditions for which records should be updated.
		$conditions = array(
			$sCondition		
		);

		$query->update($db->quoteName('#__'.$tableName))->set($fields)->where($conditions);
//echo $query->dump() ;die;
		$db->setQuery($query);

		$result = $db->execute();	
		return $result;
	}	
	
	
	static function deleteCMSData($sCondition,$nomTable){
		//à adapter selon le CMS (ici Joomla)
		
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		// delete all custom keys for user 1001.
		$conditions = array(
			//$db->quoteName('user_id') . ' = 1001'
			$sCondition
		);

		$query->delete($db->quoteName('#__'.$nomTable));
		$query->where($conditions);

		$db->setQuery($query);

		$result = $db->execute();
		return $result;		
	}
	
	static function queryCMSData($aSelect,$nomTable,$condition = '',$order = ''){
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Select all records from the user profile table where key begins with "custom.".
		// Order it by the ordering field.
		//$query->select($db->quoteName(array('user_id', 'profile_key', 'profile_value', 'ordering')));
		$query->select($db->quoteName($aSelect));
		$query->from($db->quoteName('#__'.$nomTable));
		//$query->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('custom.%'));
		if ($condition){
			$query->where($condition);
		}
		
		if ($order){
			$query->order($order);
		}
	
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
//echo $query->dump() ;die;
		// Load the results as a list of stdClass objects (see later for more options on retrieving data).
		$results = $db->loadAssocList();
		return $results;
	}
	
	static function quoteCMSData($val){
		$db = JFactory::getDbo();
		return $db->quote($results);
	}
}
?>