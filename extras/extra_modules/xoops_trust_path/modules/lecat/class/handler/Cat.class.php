<?php
/**
 * @file
 * @package lecat
 * @version $Id$
**/

if(!defined('XOOPS_ROOT_PATH'))
{
	exit;
}

/**
 * Lecat_CatObject
**/
class Lecat_CatObject extends Legacy_AbstractCategoryObject
{
	protected $_mPermitLoadedFlag = false;
	protected $_mPcatLoadedFlag = false;
	protected $_mChildrenLoadedFlag = false;
	protected $_mCatPathLoadedFlag = false;
	public $mTargetFlag = false;
	public $mProhibitedFlag = false;

	/**
	 * @public
	 * load Permit Objects of this category.
	 */
	public function loadPermit()
	{
		if ($this->_mPermitLoadedFlag == false) {
			$handler =Legacy_Utils::getModuleHandler('permit', $this->getDirname());
			$this->mPermit =& $handler->getObjects(new Criteria('cat_id', $this->get('cat_id')));
			$this->_mPermitLoadedFlag = true;
		}
	}

	/**
	 * @public
	 * load parent category Object of this category.
	 */
	public function loadPcat()
	{
		if ($this->_mPcatLoadedFlag == false) {
			$handler =Legacy_Utils::getModuleHandler('cat', $this->getDirname());
			$this->mPcat =& $handler->get($this->get('p_id'));
			$this->_mPcatLoadedFlag = true;
		}
	}

	/**
	 * @public
	 * 
	 */
	public function getDepth()
	{
		$this->loadCatPath($this->getDirname());
		return count($this->mCatPath['cat_id']) + 1;
	}

	/**
	 * @public
	 * load child categories Objects of this category.
	 */
	public function loadChildren($module)
	{
		if ($this->_mChildrenLoadedFlag == false) {
			$handler = Legacy_Utils::getModuleHandler('cat', $this->getDirname());
			$criteria = new CriteriaCompo();
			$criteria->add(new Criteria('p_id', $this->get('cat_id')));
			$criteria->setSort('weight', 'ASC');
			$children =& $handler->getObjects(new Criteria('p_id', $this->get('cat_id')));
			//check module confinement
			foreach(array_keys($children) as $key){
				if($children[$key]->checkModule($module)){
					$this->mChildren[] = $children[$key];
				}
			}
			$this->_mChildrenLoadedFlag = true;
		}
	}

	/**
	 * @public
	 * get child categories' id and title array.
	 */
	public function getChildList($module="")
	{
		$this->loadChildren($this->getDirname(), $module);
		foreach(array_keys($this->mChildren) as $key){
			$children['cat_id'][$key] = $this->mChildren[$key]->getShow('cat_id');
			$children['cat_title'][$key] = $this->mChildren[$key]->getShow('title');
		}
		return $children;
	}

	/**
	 * @public
	 * call load category function if not loaded yet.
	 */
	public function loadCatPath()
	{
		//set this category's parent cat_id
		if($this->_mCatPathLoadedFlag==false){
			$handler = Legacy_Utils::getModuleHandler('cat', $this->getDirname());
			$this->mCatPath['cat_id'] = array();
			$this->mCatPath['title'] = array();
			$this->mCatPath['modules'] = array();
			$p_id = $this->get('p_id');
			$this->_loadCatPath($p_id, $handler);
			$this->_mCatPathLoadedFlag=true;
		}
	}

	/**
	 * @protected
	 * load category path array retroactively.
	 */
	protected function _loadCatPath($p_id, $handler)
	{
		$cat =& $handler->get($p_id);
		if(is_object($cat)){
			$this->mCatPath['cat_id'][] = $cat->getShow('cat_id');
			$this->mCatPath['title'][] = $cat->getShow('title');
			$this->mCatPath['modules'][] = $cat->getShow('modules');
			$this->_loadCatPath($cat->get('p_id'), $handler);
		}
	}

	protected function _getPermit($groupid=0)
	{
		$handler = Legacy_Utils::getModuleHandler('permit', $this->getDirname());
		$criteria=new CriteriaCompo();
		$criteria->add(new Criteria('cat_id', $this->get('cat_id')));
		if(intval($groupid)>0){
			$criteria->add(new Criteria('groupid', $groupid));
		}
		return $handler->getObjects($criteria);
	}

	/**
	 * @public
	 * get the permissions for this category.
	 * At first, check the permission of this category.
	 * If the permission is not set, check the upper category's permission retroactively.
	*/
	public function getThisPermit($groupId=0)
	{
		//if this category don't have permissions, check the upper category's permissions retroactively
		if($permitArr = $this->_getPermit($groupId)){
			$this->mTargetFlag = 'cur'; //current cat has permission
			return $permitArr;
		}
		else{
			//get the category path from the top category in descendant order
			$this->loadCatPath();
			//set default permissions from Set, if any permission is set in this Category Tree
			if(! $permitArr=Lecat_Utils::getInheritPermission($this->getDirname(), $this->mCatPath['cat_id'], $groupId)){
				$permissions = $this->getDefaultPermissionForCheck();
				if(intval($groupId)>0){
					$permitArr[0] = Legacy_Utils::getModuleHandler('permit', $this->getDirname())->create();
					$permitArr[0]->set('cat_id', $this->get('cat_id'));
					$permitArr[0]->set('permissions', serialize($permissions));
					$permitArr[0]->set('groupid', $groupId);
				}
				else{
					$groupHandler =& xoops_gethandler('member');
					$group =& $groupHandler->getGroups();
					foreach(array_keys($group) as $keyM){
						$permitArr[$keyM] = Legacy_Utils::getModuleHandler('permit', $this->getDirname())->create();
						$permitArr[$keyM]->set('cat_id', $this->get('cat_id'));
						$permitArr[$keyM]->set('permissions', serialize($permissions));
						$permitArr[$keyM]->set('groupid', $group[$keyM]->get('groupid'));
					}
				}
			}
		
			$this->mTargetFlag = 'anc'; //ancestoral cat has permission
			return $permitArr;
		}
	}

	/**
	 * getDefaultPermissionForCheck
	 * 
	 * @param	void
	 * 
	 * @return	string[]
	**/
	public function getDefaultPermissionForCheck()
	{
		$permissions = array();
		$actions = Lecat_Utils::getActionList($this->getDirname());
		$i=0;
		foreach(array_keys($actions['title']) as $key){
			$permissions[$key] = $actions['default'][$key];
		}
		return $permissions;
	}

	/**
	 * @public
	 * check permission about the given uid and action.
	 * check about all groups the user is belong to.
	*/
	public function checkPermitByUid($action, $uid=0, $module="")
	{
		//check this category is for specific dirname ?
		if(! $this->checkModule($module)){
			$this->mProhibitedFlag = false;
			return false;
		}
	
		$groupHandler = Lecat_Utils::getXoopsHandler('group');
		//check group permission
		if(intval($uid)>0){
			$handler =Lecat_Utils::getXoopsHandler('user');
			$groupIds = $handler->get($uid)->getGroups();
			foreach($groupIds as $gid){
				$groups[] = $groupHandler->get($gid);
			}
		}
		else{	//case:guest
			$groups = $groupHandler->getObjects(new Criteria('group_type', 'Anonymous'));
		}
		foreach(array_keys($groups) as $keyG){
			if($this->checkPermitByGroupid($action, $groups[$keyG]->get('groupid'), $module)){
				$this->mProhibitedFlag = true;
				return true;
			}
		}
		$this->mProhibitedFlag = false;
		return false;
	}

	/**
	 * @public
	 * check permission about the given groupid and action.
	*/
	public function checkPermitByGroupid($action, $groupid=0, $module="")
	{
		//check this category is for specific dirname ?
		if(! $this->checkModule($module)) return false;
	
		$permitArr = $this->getThisPermit($groupid);
		//check illegal permission settings
		if(count($permitArr)>1){
			echo "duplicated permission settings about the combination of groupid and cat_id";
			die();
		}
		elseif(count($permitArr)==0){
			return false;
		}
		$permissions =$permitArr[0]->getPermissionArr();
		//var_dump($permissions);die();
		return (@$permissions[$action]==1) ? true : false;
	}

	/**
	 *	@public
	 *	module confinement function
	 *	check specified dirname is set in the categories' modules field ?
	 */
	public function checkModule(/*** string ***/ $module="")
	{
		if(! $module){
			return true;
		}
	
		$moduleArr = $this->getModuleArr();
		if(count($moduleArr)>0) return true;
	
		return (in_array($module, $moduleArr)) ? true : false;
	}

	/**
	 *	@public
	 *	module confinement function
	 *	get modules field of the nearest category from this one.
	 */
	public function getModuleArr()
	{
		if($this->get('modules')){
			return explode(',', $this->get('modules'));
		}
	
		$this->loadCatPath();
		//search parent categories' modules confinement
		foreach(array_keys($this->mCatPath['modules']) as $key){
			if($this->mCatPath['modules'][$key]){
				return explode(',', $this->mCatPath['modules'][$key]);
			}
		}
	
		return array();
	}
}

/**
 * Lecat_CatHandler
**/
class Lecat_CatHandler extends XoopsObjectGenericHandler
{
	/**
	 * @brief	string
	**/
	public $mTable = '{dirname}_cat';

	/**
	 * @brief	string
	**/
	public $mPrimary = 'cat_id';

	/**
	 * @brief	string
	**/
	public $mClass = 'Lecat_CatObject';

	/**
	 * __construct
	 * 
	 * @param	XoopsDatabase  &$db
	 * @param	string	$dirname
	 * 
	 * @return	void
	**/
	public function __construct(/*** XoopsDatabase ***/ &$db,/*** string ***/ $dirname)
	{
		$this->mTable = strtr($this->mTable,array('{dirname}' => $dirname));
		parent::XoopsObjectGenericHandler($db);
	}

	/**
	 * delete
	 * 
	 * @param	XoopsSimpleObject  &$obj
	 * 
	 * @return	
	**/
	public function delete(&$obj)
	{
		$handler = Legacy_Utils::getModuleHandler('permit', $this->getDirname());;
		$handler->deleteAll(new Criteria('cat_id', $obj->get('cat_id')));
		unset($handler);
	
		return parent::delete($obj);
	}


	/**
	 * getTree
	 * get Lecat_CatObject array in parent-child tree form
	 * @param	int 	$pid
	 * @param	string	$module
	 * 
	 * @return	Lecat_CatObject[]
	**/
	public function getTree(/*** int ***/ $p_id=0, /*** string ***/ $module="")
	{
		$tree = array();
		return $this->_getTree($tree, $p_id, $module);
	}

	/**
	 * _getTree
	 * 
	 * @param	Lecat_CatObject[] 	$tree
	 * @param	int 	$pid
	 * @param	string	$module
	 * 
	 * @return	Lecat_CatObject[]
	**/
	protected function _getTree(/*** Lecat_CatObject[] ***/ $tree, /*** int ***/ $p_id, /*** string ***/ $module)
	{
		$criteria = new CriteriaCompo();
		$criteria->add(new Criteria('p_id', $p_id));
		$criteria->setSort('weight');
		$catArr =$this->getObjects($criteria);
		foreach(array_keys($catArr) as $key){
			//check module confinement
			if($catArr[$key]->checkModule($module)){
				$tree[] = $catArr[$key];
				$tree = $this->_getTree($tree, $catArr[$key]->get('cat_id'), $module);
			}
		}
		return $tree;
	}

	/**
	 * filterCategory
	 * 
	 * @param	Lecat_CatObject[]	$tree
	 * @param	string	$action
	 * @param	int 	$uid
	 * @param	bool	$deleteFlag
	 * 
	 * @return	Lecat_CatObject[]
	**/
	public function filterCategory($tree, $action, $uid=0, $deleteFlag=false)
	{
		//check permission of each cat in the given tree
		foreach(array_keys($tree) as $key){
			if($tree[$key]->checkPermitByUid($action, $uid)==false && $deleteFlag==true){
				unset($tree[$key]);
			}
		}
		return $tree;
	}
}

?>
