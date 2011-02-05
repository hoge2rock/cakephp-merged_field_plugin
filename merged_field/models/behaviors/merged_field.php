<?php

class MergedFieldBehavior extends ModelBehavior {

	var $_settings = array();

	function setup($model, $settings = array()) {

		if ($this->_setup) {
			return;
		}

		$this->_settings = Set::merge($this->_settings, $settings['models']);
	}

	function beforeSave($model, $params = array()) {
		if (empty($this->_settings[$model->alias])) {
			return true;
		}
		//If the record already exists, read it from DB.
		if (!empty($model->id)) {
			$db = $model->getDataSource();
			$data = $db->read($model, array('conditions' => array("{$model->alias}.{$model->primaryKey}" => $model->id), 'recursive' => -1));
			$data = $data[0][$model->alias];
			$data = $this->_unmerge($model, $data);
		} else {
			$data = array();
		}
		//Merge
		foreach ($this->_settings[$model->alias] as $setting) {
			$mergedField = array();
			if (empty($setting['targets'])) {
				continue;
			}
			foreach (array_keys($setting['targets']) as $target) {
				$model->data[$model->alias][$target] = isset($model->data[$model->alias][$target]) ? $model->data[$model->alias][$target] : (isset($data[$target]) ? $data[$target] : $setting['targets'][$target]);
				$mergedField[$setting['fieldName']][] = $target . ':' . $model->data[$model->alias][$target];
			}
			$model->data[$model->alias][$setting['fieldName']] = implode($setting['delimiter'], $mergedField[$setting['fieldName']]);
		}
		//$model->data = array();
		//exit;
	}

	function afterFind($model, $results, $primary = false) {

		//When the model's data is not included, just skip.
		//(ex) When counting the record, no model's data is in it. 
		if (!isset($results[0][$model->alias])) {
			return $results;
		}

		$_associations = $model->__associations;
		foreach (array_keys($results) as $k) {
			//$results[$k][$model->alias] = $this->_afterFind($model, $results[$k][$model->alias]);
			$results[$k][$model->alias] = $this->_unmerge($model, $results[$k][$model->alias]);
			foreach ($_associations as $type) {
				foreach (array_keys($model->{$type}) as $assoc) {
					if (!empty($results[$k][$assoc])) {
						$results[$k][$assoc] = $this->_afterFind($model->{$assoc}, $model, $type, $results[$k][$assoc]);
					}
				}
			}
		}

		return $results;
	}

	function _afterFind($model, $assocModel, $assocType, $results) {

		$_associations = $model->__associations;
		if ($assocType == 'hasOne' || $assocType == 'belongsTo') {
			foreach ($_associations as $type) {
				foreach (array_keys($model->{$type}) as $assoc) {
					if (!empty($results[$assoc])) {
						$results[$assoc] = $this->_afterFind($model->{$assoc}, $model, $type, $results[$assoc]);
					}
				}
			}
			$results = $this->_unmerge($model, $results);
		} else {
			foreach (array_keys($results) as $k) {
				foreach ($_associations as $type) {
					foreach (array_keys($model->{$type}) as $assoc) {
						if (!empty($results[$k][$assoc])) {
							$results[$k][$assoc] = $this->_afterFind($model->{$assoc}, $model, $type, $results[$k][$assoc]);
						}
					}
				}
				$results[$k] = $this->_unmerge($model, $results[$k]);
			}
		}
		return $results;
	}

	function _unmerge($model, $data) {
		if (empty($this->_settings[$model->alias])) {
			return $data;
		}
		foreach ($this->_settings[$model->alias] as $setting) {
			if (empty($data[$setting['fieldName']])) {
				continue;
			}
			$unmerged_tmp = explode($setting['delimiter'], $data[$setting['fieldName']]);
			$targets = array_keys($setting['targets']);
			$targets = array_flip($targets);
			foreach ($unmerged_tmp as $val) {
				if (strpos($val, ':') !== false) {
					list($k, $v) = explode(':', $val, 2);
					if (isset($targets[$k])) {
						$data[$k] = $v;
					}
				}
			}
		}
		return $data;
	}

}