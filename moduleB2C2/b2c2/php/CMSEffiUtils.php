<?php
defined( '_JEXEC' ) or die; //appel uniquement depuis le CMS

		
class CMSEffiUtils{
	
	static function getURLProjet(){
		return JURI::base().'projet/';
	}
		

		
	static function getCMSUriBase(){
		return JURI::base();
	}
	
	static function getCMSUriProjBase(){
		$cur_dir = preg_split('#(/|\\\\)#', __dir__);
		$cur_dir = $cur_dir[count($cur_dir)-2];
		return CMSEffiUtils::getCMSUriBase().'libraries/effinergie/sourcerer/b2c2/'.$cur_dir .'/';
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
		$columns = array_keys($Data);

		// Insert values.
		$values = array_values($Data);
		
		

		// Prepare the insert query.
		$query
			->insert($db->quoteName('#__'.$tableName))
			->columns($db->quoteName($columns))			
			->values(implode(',',$db->quote($values)));

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

		}

		// Conditions for which records should be updated.
		$conditions = array(
			$sCondition		
		);

		$query->update($db->quoteName('#__'.$tableName))->set($fields)->where($conditions);

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
			$sCondition
		);

		$query->delete($db->quoteName('#__'.$nomTable));
		$query->where($conditions);

		$db->setQuery($query);

		$result = $db->execute();
		return $result;		
	}
	
	static function queryCMSData($aSelect,$aNomTable,$condition = '',$order = '',$aOption=[]){
		if (is_string($aNomTable)){
			$aNomTable = [$aNomTable];
		}
		
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Select all records from the user profile table where key begins with "custom.".
		// Order it by the ordering field.
		
		$sSelect = $db->quoteName($aSelect);
		if (isset($aOption['foundRows']) ){
			array_unshift($sSelect, 'SQL_CALC_FOUND_ROWS 1');
		}
		
		$query->select($sSelect);
		

		foreach ($aNomTable as $i=>$nomTable){
			$aNt = explode(' ',$nomTable);
			$aNomTable[$i] = $db->quoteName('#__'.$aNt[0]);
			if (isset($aNt[1])){
				$aNomTable[$i].=' '.$db->quoteName($aNt[1]);
			}
		}
		$query->from(implode(', ',$aNomTable));
		
		if ($condition){
			$query->where($condition);
		}
		
		if ($order){
			$query->order($order);
		}
		
		if (isset($aOption['limit']) && isset($aOption['offset']) ){
			$query->setLimit($aOption['limit'],$aOption['offset']);
		}
	
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
//echo $query->dump() ;die;
		// Load the results as a list of stdClass objects (see later for more options on retrieving data).
		$results = $db->loadAssocList();
		return $results;
	}
	
	static function getQueryFoundRows(){
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('FOUND_ROWS() as nbr');
		$db->setQuery($query);
		$results = $db->loadAssocList();
		return $results[0]['nbr'];
	}
	static function quoteCMSData($val){
		$db = JFactory::getDbo();
		return $db->quote($val);
	}
}
?>