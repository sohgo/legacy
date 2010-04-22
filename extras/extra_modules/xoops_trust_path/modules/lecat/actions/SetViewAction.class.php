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

require_once LECAT_TRUST_PATH . '/class/AbstractViewAction.class.php';

/**
 * Lecat_SetViewAction
**/
class Lecat_SetViewAction extends Lecat_AbstractViewAction
{
	/**
	 * _getId
	 * 
	 * @param	void
	 * 
	 * @return	int
	**/
	protected function _getId()
	{
		return $this->mRoot->mContext->mRequest->getRequest('set_id');
	}

	/**
	 * &_getHandler
	 * 
	 * @param	void
	 * 
	 * @return	Lecat_SetHandler
	**/
	protected function &_getHandler()
	{
		$handler =& $this->mAsset->getObject('handler', 'set');
		return $handler;
	}

	/**
	 * prepare
	 * 
	 * @param	void
	 * 
	 * @return	void
	**/
	public function prepare()
	{
		parent::prepare();
		$this->mObject->loadTree();
	}

	/**
	 * executeViewSuccess
	 * 
	 * @param	XCube_RenderTarget	&$render
	 * 
	 * @return	void
	**/
	public function executeViewSuccess(/*** XCube_RenderTarget ***/ &$render)
	{
		$render->setTemplateName($this->mAsset->mDirname . '_set_view.html');
		$render->setAttribute('lecatDirname', $this->mAsset->mDirname);
		$render->setAttribute('tree', $this->mObject->mTree);
		$render->setAttribute('object', $this->mObject);
		$render->setAttribute('actions', $this->mObject->getDefaultPermissionList());
	}

	/**
	 * executeViewError
	 * 
	 * @param	XCube_RenderTarget	&$render
	 * 
	 * @return	void
	**/
	public function executeViewError(/*** XCube_RenderTarget ***/ &$render)
	{
		$this->mRoot->mController->executeRedirect('./index.php?action=SetList', 1, _MD_LECAT_ERROR_CONTENT_IS_NOT_FOUND);
	}
}

?>