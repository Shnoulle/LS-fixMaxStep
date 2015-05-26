<?php
/**
 * fixMaxStep Plugin for LimeSurvey
 * Fix the index for tokenized survey and with token answer persistence
 * Core LimeSUrvey behaviour is to set max step to the last step submitted, then some user don't have complete index 
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @license GPL v3
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */
 
class fixMaxStep extends PluginBase {
  static protected $description = 'Fixing max step with token persistance enabled and index is set';
  static protected $name = 'fixMaxStep';
 
  public function __construct(PluginManager $manager, $id) {
    parent::__construct($manager, $id);
    $this->subscribe('beforeSurveyPage');
  }
 
  public function beforeSurveyPage() {
    $oEvent = $this->event;
    $iSurveyId=$oEvent->get('surveyId');
    // Validate if we need to fix maxstep
    $oSurvey=Survey::model()->findByPk($iSurveyId);

    // Find the token by session
    $sSessionToken=(isset($_SESSION["survey_{$iSurveyId}"]['token'])) ? $_SESSION["survey_{$iSurveyId}"]['token'] : null;
    // Find the token by param : if it's set but not $iRespondeId: it's a new session
    $sToken=$this->api->getRequest()->getParam('token',$sSessionToken);
    // Get some step
    if($oSurvey && $sToken && $oSurvey->questionindex && $oSurvey->active=="Y" && $oSurvey->tokenanswerspersistence=="Y" && $oSurvey->anonymized=="N")
    {
      $iPostedStep=$this->api->getRequest()->getPost('thisstep');
      $iMaxSessionStep=(isset($_SESSION["survey_{$iSurveyId}"]['maxstep'])) ? $_SESSION["survey_{$iSurveyId}"]['maxstep'] : null;
      $iRespondeId=(isset($_SESSION["survey_{$iSurveyId}"]['srid'])) ? $_SESSION["survey_{$iSurveyId}"]['srid'] : null;
      // Attention : we test only $oSurvey->active, we don't test of table survey exist : not needed.
      // Get if token table and token exist
      $bTokenExists = tableExists('{{tokens_' . $iSurveyId . '}}');
      if(!$bTokenExists)
          return;
      // If token is set , but not response : find if we need to set maxstep
      if(!$iRespondeId)
      {
        // Only if $sToken useleft <=1
        if ($oToken=TokenDynamic::model($iSurveyId)->find("token =:token",array(':token' => $sToken)))
        {
          if($oToken->usesleft<=1)
          {
            $oResponse=SurveyDynamic::model($iSurveyId)->find("token =:token",array(':token' => $sToken));
            $iResponseStep=($oResponse) ? $oResponse->lastpage : null;
            $iRespondeId=($oResponse) ? $oResponse->id : null;
            $oSavedControl=SavedControl::model()->find("sid =:sid AND srid=:srid",array(':sid'=>$iSurveyId,':srid'=>$iRespondeId));
            $iSavedStep=($oSavedControl) ? $oSavedControl->saved_thisstep : null;
            $iMaxStep=max($iSavedStep,$iResponseStep,$iPostedStep,$iMaxSessionStep);
          }
          // What happen if useleft > 1 ?
        }
      }
      else
      {
        // Find the $oResponseId
        $oResponse=SurveyDynamic::model($iSurveyId)->find("id =:srid",array(':srid' => $iRespondeId));
        $iResponseStep=($oResponse) ? $oResponse->lastpage : null;
        $oSavedControl=SavedControl::model()->find("sid =:sid AND srid=:srid",array(':sid'=>$iSurveyId,':srid'=>$iRespondeId));
        $iSavedStep=($oSavedControl) ? $oSavedControl->saved_thisstep : null;
        // Find the real max step
        $iMaxStep=max($iSavedStep,$iResponseStep,$iPostedStep,$iMaxSessionStep);
      }
      if(!empty($iMaxStep))
      {
        $_SESSION["survey_{$iSurveyId}"]['maxstep']=$iMaxStep;
        if($iSavedStep < $iMaxStep)
        {
            if(!$oSavedControl)
            {
              $oSavedControl=new SavedControl;
              $oSavedControl->sid=$iSurveyId;
              $oSavedControl->srid=$iRespondeId;
            }
            $oSavedControl->saved_thisstep=$iMaxStep;
            if(!$oSavedControl->save())
              tracevar($oSavedControl->getErrors());
        }
      }
    }
  }
}
