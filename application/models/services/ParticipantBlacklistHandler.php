<?php

namespace LimeSurvey\Models\Services;

use InvalidArgumentException;
use \LimeSurvey\Datavalueobjects\BlacklistResult;

class ParticipantBlacklistHandler
{
    /**
     * @param \Token $token
     * @return \LimeSurvey\Datavalueobjects\BlacklistResult
     */
    public function addToBlacklist($token)
    {
        $participant = $this->getCentralParticipantFromToken($token);
        if (empty($participant)) {
            return new BlacklistResult(false, gT("No CPDB participant found."));
        }

        // Add participant to the blacklist
        if ($participant->blacklisted == "Y") {
            return new BlacklistResult(true, gT("You have already been removed from the central participants list for this site"));
        } else {
            // Mark the participant as blacklisted in the CPDB
            $participant->blacklisted = 'Y';
            $participant->save();

            $result = new BlacklistResult(true, gT("You have been removed from the central participants list for this site"));

            // Remove or blacklist participant in current surveys if needed
            if (\Yii::app()->getConfig('deleteblacklisted')) {
                $surveyIds = $this->removeParticipantFromAllSurveys($participant);
            } elseif (\Yii::app()->getConfig('blacklistallsurveys')) {
                $surveyIds = $this->optoutParticipantFromAllSurveys($participant);
            }
            if (!empty($surveyIds)) {
                $result->appendMessage(sprintf(gT("You were also removed from %d surveys on this site."), count($surveyIds)));
            }

            return $result;
        }
    }

    /**
     * Returns the CPDB participant corresponding to the given Token.
     * @param \Token $token
     * @return \Participant|null
     */
    private function getCentralParticipantFromToken($token)
    {
        $participant = null;
        // Try to match by participant ID
        if (!empty($token->participant_id)) {
            $participant = \Participant::model()->findByPk($token->participant_id);
        }
        // TODO: Should we also try to match by email?
        return $participant;
    }

    /**
     * Removes the participant from all surveys currently present in the system that use tokens
     * @param \Participant $participant
     * @return int[] the list of survey IDs from which the participant was removed
     */
    private function removeParticipantFromAllSurveys($participant)
    {
        if (empty($participant->participant_id)) {
            throw new InvalidArgumentException(gT("Participant ID cannot be empty"));
        }
        $surveys = \Survey::model()->findAllByAttributes(['usetokens' => 'Y']);
        /** @var int[] the list of survey IDs from which the participant was removed */
        $removedSurveyIds = [];
        foreach ($surveys as $survey) {
            if ($survey->hasTokensTable) {
                $count = \Token::model($survey->sid)->deleteAllByAttributes(['participant_id' => $participant->participant_id]);
                if ($count > 0) {
                    $removedSurveyIds[] = $survey->sid;
                }
            }
        }
        return $removedSurveyIds;
    }

    /**
     * Marks the participant as "opted out" on all surveys currently present in the system that use tokens
     * @param \Participant $participant
     * @return int[] the list of survey IDs from which the participant was opted out
     */
    private function optoutParticipantFromAllSurveys($participant)
    {
        if (empty($participant->participant_id)) {
            throw new InvalidArgumentException(gT("Participant ID cannot be empty"));
        }
        $surveys = \Survey::model()->findAllByAttributes(['usetokens' => 'Y']);
        /** @var int[] the list of survey IDs from which the participant was opted out */
        $optedoutSurveyIds = [];
        foreach ($surveys as $survey) {
            if ($survey->hasTokensTable) {
                $token = \Token::model($survey->sid)->findByAttributes(['participant_id' => $participant->participant_id], "emailstatus <> 'OptOut'");
                if (!empty($token)) {
                    $token->emailstatus = 'OptOut';
                    $token->save();
                    $optedoutSurveyIds[] = $survey->sid;
                }
            }
        }
        return $optedoutSurveyIds;
    }
}