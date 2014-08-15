<?php

namespace Gallery\DatabaseService;

class DatabaseService {
  
  private $conn = null,
          $app = null;
          
  function __construct($app)
  {
    $this->app = $app;
  }
  
  function connect()
  {
		if ($this->conn) return;

	  $this->conn = mysql_connect($this->app['database.host'], $this->app['database.user'], $this->app['database.pass']);
	  if (!$this->conn)
	  {
	    die('connection error');
	  }
	  mysql_select_db($this->app['database.base']);
  }

  function execute($query)
  {
    
	  return mysql_query($query);
  }
  
  function getObjects($objectType, $mediaFormats = array(), $tags = array(), $orderBy = 'position', $getCrossings = false)
  {
    $fieldList = array_keys($this->fieldsToFieldList($this->app['gallery.objects'][$objectType]['fields'], $this->app['gallery.languages']));
    
    $ret = array();
    
    $query = 'select id, `' . implode('`, `', $fieldList) . '` from ' . $objectType . ' order by `' . $orderBy . '` ASC, `created_at` DESC';
    $result = $this->execute($query);
    while ($row = mysql_fetch_row($result))
    {
      $ret[$row[0]] = array(
        'id'   => $row[0]
      );
      $i = 1;
      foreach($fieldList as $field)
      {
        $ret[$row[0]][$field] = $row[$i++];
      }
      
      $ret[$row[0]]['tags'] = $this->getTags($objectType, $row[0]); // TODO: make one query for all objects
      foreach($tags as $tag) {
        if (!in_array($tag, $ret[$row[0]]['tags'])) {
          unset($ret[$row[0]]);
        }
      }
         
    }

    if ($mediaFormats && count($ret))
    {
			if (!is_array($mediaFormats)) {
				$mediaFormats = array($mediaFormats);
			}
			
      $query = 'select ' . $objectType . '_id, url, title from ' . $objectType . '_media where ';
      if ($mediaFormats != array('all')) {
				$mediaStrs = array();
				foreach ($mediaFormats as $format) {
					$mediaStrs[] = '\''.$format.'\'';
				}
        $query .= 'type IN  ('.implode(',', $mediaStrs).') and ';
      }
      $query .=  $objectType . '_id in (' . implode(',', array_keys($ret)) . ')';
      $result = $this->execute($query);
      if ($result) {
        while ($row = mysql_fetch_row($result))
        {
					if (strpos($row[1], 'image') !== false) {
	          $ret[$row[0]]['images'][] = $row[1];						
					} else {
	          $ret[$row[0]]['sounds'][] = array(
							'url' => $row[1],
							'title' => $row[2]
	          );						
					}
        }        
      }
    }
    
    if ($getCrossings && isset($this->app['gallery.objects'][$objectType]['crossings']) && ($crossings = $this->app['gallery.objects'][$objectType]['crossings'])) {    
      foreach($crossings as $crossing) {
        $fieldList = array_keys($this->fieldsToFieldList($this->app['gallery.objects'][$crossing]['fields'], $this->app['gallery.languages']));
        
        $query = 'select ' . $crossing . '_id, `' . $objectType . '_id` from `' . $objectType . '_' . $crossing . '` where `' . $objectType . '_id` in (' . implode(',', array_keys($ret)) . ')';
        $result = $this->execute($query);
        while ($row = mysql_fetch_row($result))
        {
          $ret[$row[1]][$crossing][] = $row[0];
        }

      }

    }
    
    
    return $ret;
  }

  function getObject($objectType, $id)
  {
    $fieldList = array_keys($this->fieldsToFieldList($this->app['gallery.objects'][$objectType]['fields'], $this->app['gallery.languages']));
    
    $ret = array();
    $result = $this->execute('select id, `' . implode('`, `', $fieldList) . '` from ' . $objectType . ' where id = ' . $id);
    while ($row = mysql_fetch_row($result))
    {
      $ret = array(
        'id'   => $row[0],
      );
      $i = 1;
      foreach($fieldList as $field)
      {
        $ret[$field] = $row[$i++];
      }
    }
    
    $ret['media'] = array(); // in case there is no medium
    if (isset($this->app['gallery.objects'][$objectType]['media']) && $this->app['gallery.objects'][$objectType]['media']) {
			if ($objectType === 'artist') {
				$query = 'select id, name, type, url, created_at, position, description from ' . $objectType . '_media where ' . $objectType . '_id = ' . $id . ' order by type asc, position asc, id desc';						
			} else {
				$query = 'select id, name, type, url, created_at, position from ' . $objectType . '_media where ' . $objectType . '_id = ' . $id . ' order by type asc, position asc, id desc';
			}
      $result = $this->execute($query);
      while ($row = mysql_fetch_row($result))
      {
        $data = array(
          'id'   => $row[0],
          'name' => $row[1],
          'type' => $row[2],
          'url'  => $row[3],
          'position'  => $row[5]
        );
				if ($objectType === 'artist') {
					$data['description'] = $row[6];
				}
				$ret['media'][] = $data;
      }      
    }

    
    $ret['docs'] = array(); // in case there is no docs
    if (isset($this->app['gallery.objects'][$objectType]['doc']) && $this->app['gallery.objects'][$objectType]['doc']) {
      $result = $this->execute('select id, name, type, url, preview_url, created_at, position from ' . $objectType . '_doc where ' . $objectType . '_id = ' . $id . ' order by type asc, position asc, id desc');
        while ($row = mysql_fetch_row($result))
        {
          $ret['docs'][] = array(
            'id'           => $row[0],
            'name'         => $row[1],
            'type'         => $row[2],
            'url'          => $row[3],
            'preview_url'  => $row[4],
            'position'     => $row[6]
          );
        }      
    }
    
    if (isset($this->app['gallery.objects'][$objectType]['crossings']) && ($crossings = $this->app['gallery.objects'][$objectType]['crossings'])) {    
      foreach($crossings as $crossing) {
        $fieldList = array_keys($this->fieldsToFieldList($this->app['gallery.objects'][$crossing]['fields'], $this->app['gallery.languages']));
        
        $query = 'select id, `' . implode('`, `', $fieldList) . '` from `' . $crossing . '` where id in (select ' . $crossing . '_id from `' . $objectType . '_' . $crossing . '` where `' . $objectType . '_id` = ' . $id . ')';
        $result = $this->execute($query);
        while ($row = mysql_fetch_row($result))
        {
          $d = array(
            'id'   => $row[0],
          );
          $i = 1;
          foreach($fieldList as $field)
          {
            $d[$field] = $row[$i++];
          }
          $ret[$crossing][] = $d;
        }

      }

    }

    $ret['tags'] = $this->getTags($objectType, $id);
    
    return $ret;
  }
  
  function getTags($objectType, $objectId)
  {
    if (!isset($this->app['gallery.objects'][$objectType]['tag.classes']) || !count($this->app['gallery.objects'][$objectType]['tag.classes']))
    {
      return array();
    }

    $ret = array(); // in case there is no medium
    $potentialTagTable = $objectType . '_potential_tag';
    $tagTable = $objectType . '_tag';
    
    $result = $this->execute('select ' . $objectType . '_potential_tag_id, name from ' . $tagTable . ', ' . $potentialTagTable . ' where ' . $tagTable . '.' . $objectType . '_id = '. $objectId . ' and ' . $tagTable . '.' . $objectType . '_potential_tag_id = ' . $potentialTagTable . '.id');
    while ($row = mysql_fetch_row($result))
    {
      $ret[$row[0]] = $row[1];
    }
    
    return $ret;
  }
  
  function getNextObject($objectType, $id)
  {
    $result = $this->execute('select id from ' . $objectType . ' where position > (select position from ' . $objectType . ' where id = ' . $id . ') order by position asc limit 1');
    if ($row = mysql_fetch_row($result)){
      return $this->getObject($objectType, $row[0]);
    }
    
    return null;
  }

  function getPreviousObject($objectType, $id)
  {
    $result = $this->execute('select id from ' . $objectType . ' where position < (select position from ' . $objectType . ' where id = ' . $id . ') order by position desc limit 1');
    if ($row = mysql_fetch_row($result)){
      return $this->getObject($objectType, $row[0]);
    }
    
    return null;
  }
  
  function saveObject($objectType, $fields, $id = null)
  {
    if (!count($fields))
    {
      return;
    }
    
    if (isset($fields['tags']))
    {
      $tags = $fields['tags'];
      unset($fields['tags']);      
    }
    else
    {
      $tags = array();
    }
    
    if (isset($fields['crossings'])) {
      $crossings = $fields['crossings'];
      unset($fields['crossings']);
    } else {
      $crossings = array();
    }

    if (!is_numeric($id))
    {
      $fieldNamesStr = implode('`,`', array_keys($fields));
      
      $fieldVars = array();
      foreach ($fields as $field)
      {
        $fieldVars[] = addslashes($field);
      }
      //$fieldVars = $fields;
      
      $fieldStr = '\'' . implode('\',\'', $fieldVars) . '\'';
      $query = 'insert into ' . $objectType . ' (`' . $fieldNamesStr . '`) values (' . $fieldStr . ')';
      if (!$this->execute($query))
      {
        die('error while saving object 2');
      }

      $id = mysql_insert_id();
    }
    else
    {
      $query = 'update ' . $objectType . ' set ';
      $isFirst = true;
      foreach($fields as $name => $value)
      {
        if ($isFirst)
        {
          $isFirst = false;
        }
        else
        {
          $query .= ', ';
        }
//        $query .= '`' . $name . '` = \'' . addslashes($value) . '\' ';
        $query .= '`' . $name . '` = \'' . addslashes($value) . '\' ';
      }
      $query .=' where id =' . $id;   
      if (!$this->execute($query))
      {
        die('error while updating');        
      }   
      
      // tags
      $currentTags = $this->getTags($objectType, $id);
      if (!(
        (count($currentTags) == count($tags))
        &&
        !array_diff($currentTags, $tags)
      ))
      {
        $this->execute('delete from ' . $objectType . '_tag where ' . $objectType . '_id = ' . $id);
        foreach($tags as $tagId)
        {
          $this->execute('insert into ' . $objectType . '_tag(' . $objectType . '_id, ' . $objectType . '_potential_tag_id) values (' . $id . ', ' . $tagId . ')');
        }
      }
      
      // crossings
      foreach($crossings as $table => $values) {
        $query = 'delete from ' . $objectType . '_' . $table . ' where ' . $objectType . '_id = ' . $id;
        $this->execute($query);
        $IdsToInsert = array();
        foreach ($values as $value) {
          $IdsToInsert[] = '(' . $id . ', ' . $value . ')';
        }
        if ($IdsToInsert) {
          $query = 'insert into ' . $objectType . '_' . $table . ' (' . $objectType . '_id, ' . $table . '_id) values ';
          $query .= implode(',', $IdsToInsert);
          $this->execute($query);          
        }
      }

    }
    return $this->getObject($objectType, $id);
    
  }
  
  function getMedium($objectType, $mediumType, $id)
  {
		
		 $isImage = in_array($mediumType, array_keys($this->app['gallery.objects'][$objectType]['media']['image.formats']));
		 $type = ($isImage ? 'image' : 'sound');		 
		 		
    if (isset($this->app['gallery.objects'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'])) {
      $fields = $this->app['gallery.objects'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'];
      $fieldList = array_keys($this->fieldsToFieldList($fields, $this->app['gallery.languages']));      
    } elseif (isset($this->app['gallery.pages'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'])) {
      $fields = $this->app['gallery.pages'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'];
      $fieldList = array_keys($this->fieldsToFieldList($fields, $this->app['gallery.languages']));      
    } else {
      $fieldList = array();
    }
    
    $ret = array();
    $query = 'select id, url';
    if (count($fieldList)) {
      $query .= ', `' . implode('`, `', $fieldList) . '`';
    }
    $query .= ' from ' . $objectType . '_media where id = ' . $id;
    $result = $this->execute($query);
    while ($row = mysql_fetch_row($result))
    {
      $ret = array(
        'id'   => $row[0],
        'url' => $row[1],
      );
      $i = 2;
      foreach($fieldList as $field)
      {
        $ret[$field] = $row[$i++];
      }
    }
    
    return $ret;
  }  
  
  function getDoc($objectType, $docType, $id)
  {
    if (isset($this->app['gallery.objects'][$objectType]['doc']['formats'][$docType]['fields'])) {
      $fields = $this->app['gallery.objects'][$objectType]['doc']['formats'][$docType]['fields'];
      $fieldList = array_keys($this->fieldsToFieldList($fields, $this->app['gallery.languages']));      
    } elseif (isset($this->app['gallery.pages'][$objectType]['doc']['formats'][$docType]['fields'])) {
      $fields = $this->app['gallery.pages'][$objectType]['doc']['formats'][$docType]['fields'];
      $fieldList = array_keys($this->fieldsToFieldList($fields, $this->app['gallery.languages']));
    } else {
      $fieldList = array();
    }
    
    $ret = array();
    $query = 'select id, url, preview_url';
    if (count($fieldList)) {
      $query .= ', `' . implode('`, `', $fieldList) . '`';
    }
    $query .= ' from ' . $objectType . '_doc where id = ' . $id;

    $result = $this->execute($query);
    while ($row = mysql_fetch_row($result))
    {
      $ret = array(
        'id'   => $row[0],
        'url' => $row[1],
        'preview_url' => $row[2],
      );
      $i = 3;
      foreach($fieldList as $field)
      {
        $ret[$field] = $row[$i++];
      }
    }
    
    return $ret;
  }    
  
  function saveMedium($objectType, $filename, $type, $project_id, $fields, $mediumId = null) {
    
    unset($fields['image_type']);
    
    if (!$mediumId || ($mediumId == 'new')) {
      $fieldNamesStr = implode('`,`', array_keys($fields));
      $fieldVars = $fields;
      $fieldStr = '\'' . implode('\',\'', $fieldVars) . '\'';
      $query = 'insert into ' . $objectType . '_media (url, type, ' . $objectType . '_id';
      if (count($fields)) {
        $query .= ', `' . $fieldNamesStr . '`';
      }
      $query .= ') values (\'' . $filename . '\', \'' . $type . '\', ' . $project_id;
      if (count($fields)) {
        $query .= ', ' . $fieldStr;
      }      
      $query .= ')';
    } else {
      $query = 'update ' . $objectType . '_media set ';
      $isFirst = true;
      foreach($fields as $name => $value)
      {
        if ($isFirst)
        {
          $isFirst = false;
        }
        else
        {
          $query .= ', ';
        }
        $query .= '`' . $name . '` = \'' . $value . '\' ';
      }
      $query .=' where id =' . $mediumId;
    }

    return $this->execute($query);
  }
  
  function saveDoc($objectType, $title, $filename, $previewFilename, $type, $project_id, $fields, $docId = null) {
    
    unset($fields['doc_type']);
    
    if (!$docId || ($docId == 'new')) {
      $fieldNamesStr = implode('`,`', array_keys($fields));
      $fieldVars = $fields;
      $fieldStr = '\'' . implode('\',\'', $fieldVars) . '\'';
      $query = 'insert into ' . $objectType . '_doc (url, preview_url, type, ' . $objectType . '_id';
      if (count($fields)) {
        $query .= ', `' . $fieldNamesStr . '`';
      }
      $query .= ') values (\'' . $filename . '\', \'' . $previewFilename . '\', \'' . $type . '\', ' . $project_id;
      if (count($fields)) {
        $query .= ', ' . $fieldStr;
      }      
      $query .= ')';
    } else {
      $query = 'update ' . $objectType . '_doc set ';
      $isFirst = true;
      foreach($fields as $name => $value)
      {
        if ($isFirst)
        {
          $isFirst = false;
        }
        else
        {
          $query .= ', ';
        }
        $query .= '`' . $name . '` = \'' . $value . '\' ';
      }
      $query .=' where id =' . $mediumId;
    }    

    return $this->execute($query);
  }  

  function deleteObject($objectType, $id)
  {
    if (!is_numeric($id))
    {
      return;
    }
    return $this->execute('delete from ' . $objectType . ' where id = ' . $id);
  }

  function deleteMedia($objectType, $mediumId)
  {
    if (!is_numeric($mediumId))
    {
      return;
    }
    return $this->execute('delete from ' . $objectType . '_media where id = ' . $mediumId);
  }
  
  function deleteDoc($objectType, $docId)
  {
    if (!is_numeric($docId))
    {
      return;
    }
    return $this->execute('delete from ' . $objectType . '_doc where id = ' . $docId);
  }  

 
  function fieldsToFieldList($fields, $languages)
  {
    if (!$fields) {
      return array();
    }

    $fieldList = array();
    foreach ($fields as $fieldName => $field) {
      if (isset($field['multilingual']) && $field['multilingual'])
      {
        foreach(array_keys($languages) as $lang)
        {
          $fieldList[$fieldName . '_' . $lang] = array_merge($field, array('lang' => $lang));
        }
      }
      else
      {
         $fieldList[$fieldName] = $field;
      }
    }

    return $fieldList; 
  }  
  
  function reorderProjects($objectType, $objectIdToPosition)
  {
    $result = $this->execute('select id, position from ' . $objectType . ' order by position asc');
    while ($row = mysql_fetch_row($result))
    {
      list($id, $position) = $row;
      if ($objectIdToPosition[$id] != $position)
      {
        $this->execute('update ' . $objectType . ' set position = ' . $objectIdToPosition[$id] . ' where id = ' . $id);
      }
    }
  }
  
  function reorderMedia($objectType, $mediumType, $objectIdToPosition)
  {
    $result = $this->execute('select id, position from ' . $objectType . '_media where type = \'' . addslashes($mediumType) . '\' order by position asc');
    while ($row = mysql_fetch_row($result))
    {
      list($id, $position) = $row;
      if ($objectIdToPosition[$id] != $position)
      {
        $this->execute('update ' . $objectType . '_media set position = ' . $objectIdToPosition[$id] . ' where id = ' . $id);
      }
    }
  }  
  
  function reorderDocs($objectType, $mediumType, $objectIdToPosition)
  {
    $result = $this->execute('select id, position from ' . $objectType . '_doc where type = \'' . addslashes($mediumType) . '\' order by position asc');
    while ($row = mysql_fetch_row($result))
    {
      list($id, $position) = $row;
      if ($objectIdToPosition[$id] != $position)
      {
        $this->execute('update ' . $objectType . '_doc set position = ' . $objectIdToPosition[$id] . ' where id = ' . $id);
      }
    }
  }    
  
  function savePage($fields, $pageName)
  {
    if (!count($fields))
    {
      return;
    }

    $fieldNamesStr = implode('`,`', array_keys($fields));
    $fieldStr = '\'' . implode('\',\'', $fields) . '\'';
    $query = 'replace into `' . $pageName . '` (id, `' . $fieldNamesStr . '`) values (1, ' . $fieldStr . ')';
    if (!$this->execute($query))
    {
      die('error while inserting');
    }
    return $this->getPage($pageName);
    
  }
  
  function getPage($pageName)
  {
    if (isset($this->app['gallery.pages'][$pageName]['fields'])) {
      $fieldList = array_keys($this->fieldsToFieldList($this->app['gallery.pages'][$pageName]['fields'], $this->app['gallery.languages']));      
    } else {
      $fieldList = array();
    }
    
    $ret = array();
    $query = 'select id ';
    
    if (count($fieldList)) {
     $query .= ', `' . implode('`, `', $fieldList) . '`';
    }
    $query .= ' from `' . $pageName . '` limit 1';
    $result = $this->execute($query);
    if ($row = mysql_fetch_row($result))
    {
      $ret['name'] = $pageName;
      $ret['id'] = $row[0];
      $i = 1;
      foreach($fieldList as $field)
      {
        $ret[$field] = $row[$i++];
      }
    }
    else
    {
      foreach($fieldList as $field)
      {
        $ret[$field] = null;
      }
    }      
    
    $ret['media'] = array(); // in case there is no medium
    $result = $this->execute('select id, name, type, url, created_at, position from `' . $pageName . '_media` order by position asc');
    while ($row = mysql_fetch_row($result))
    {
      $ret['media'][] = array(
        'id'   => $row[0],
        'name' => $row[1],
        'type' => $row[2],
        'url'  => $row[3],
        'position'  => $row[5]
      );
    }
    
    $ret['docs'] = array(); // in case there is no docs
    if (isset($this->app['gallery.pages'][$pageName]['doc']) && $this->app['gallery.pages'][$pageName]['doc']) {
      $result = $this->execute('select id, name, type, url, preview_url, created_at, position from ' . $pageName . '_doc  order by type asc, position asc, id desc');
        while ($row = mysql_fetch_row($result))
        {
          $ret['docs'][] = array(
            'id'           => $row[0],
            'name'         => $row[1],
            'type'         => $row[2],
            'url'          => $row[3],
            'preview_url'  => $row[4],
            'position'     => $row[6]
          );
        }      
    }    
    
    return $ret;
  }
  
  function getCategoryTags($objectType, $category)
  {
    $result = $this->execute('select id, name from `' . $objectType . '_potential_tag` where category = \'' . $category . '\' order by name asc');
    $ret = array();
    while ($row = mysql_fetch_row($result))
    {
      $ret[] = array(
        'id'   => $row[0],
        'name' => $row[1]
      );
    }
    
    return $ret;
    
  }

  function addTag($objectType, $tag, $category)
  {
    return $this->execute('insert into `' . $objectType . '_potential_tag` (name, category) values (\'' . $tag . '\', \'' . $category . '\')');
  }
  
  function deleteTag($objectType, $tagId)
  {
    $this->execute('delete from `' . $objectType . '_tag` where tag_id = \'' . $tagId . '\'');
    $this->execute('delete from `' . $objectType . '_potential_tag` where id = \'' . $tagId . '\'');
  }
  
  function getPotentialTags($objectType)
  {
    if (!isset($this->app['gallery.objects'][$objectType]['tag.classes']) || !count($this->app['gallery.objects'][$objectType]['tag.classes']))
    {
      return array();
    }    
    $result = $this->execute('select id, name, category from `' . $objectType . '_potential_tag` order by category desc, name asc');
    $ret = array();
    while ($row = mysql_fetch_row($result))
    {
      if (!isset($ret[$row[2]]))
      {
        $ret[$row[2]] = array();
      }
      
      $ret[$row[2]][] = array(
        'id'   => $row[0],
        'name' => $row[1],
      );
    }
    
    return $ret;
  }
  
  function getPotentialCrossingValues($objectType)
  {
    if (!isset($this->app['gallery.objects'][$objectType]['crossings']) || !count($this->app['gallery.objects'][$objectType]['crossings']))
    {
      return array();
    }    
    
    foreach($this->app['gallery.objects'][$objectType]['crossings'] as $crossing) {
      $ret[$crossing] = $this->getObjects($crossing);
    }
    
    return $ret;
  }  
  
}