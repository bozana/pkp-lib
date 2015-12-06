{**
 * templates/controllers/grid/representations/form/assignPublicIdentifiersForm.tpl
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 *} 
 <script>
    $(function() {ldelim}
        // Attach the form handler.
        $('#assignPublicIdentifierForm').pkpHandler(
            '$.pkp.controllers.form.AjaxFormHandler',
            {ldelim}
                trackFormChanges: true
            {rdelim}
        );
    {rdelim});
</script>
{if $pubObject instanceof Representation}
    <form class="pkp_form" id="assignPublicIdentifierForm" method="post" action="{url component="grid.articleGalleys.ArticleGalleyGridHandler" op="updatePubIds" submissionId=$pubObject->getSubmissionId() representationId=$pubObject->getId() escape=false}">
{elseif $pubObject instanceof SubmissionFile}
    <form class="pkp_form" id="assignPublicIdentifierForm" method="post" action="{url component="grid.articleGalleys.ArticleGalleyGridHandler" op="setProofFileCompletion" fileId=$pubObject->getFileId() revision=$pubObject->getRevision() submissionId=$pubObject->getSubmissionId() approval=$approval confirmed=true escape=false}">
{/if}
{if $approval}
    {fbvFormArea id="identifiers" title="submission.identifiers"}
        {foreach from=$pubIdPlugins item=pubIdPlugin}
            {assign var=pubObjectType value=$pubIdPlugin->getPubObjectType($pubObject)}
            {if $pubIdPlugin->isObjectTypeEnabled($pubObjectType, $currentContext->getId())}
                {assign var=pubIdAssignFile value=$pubIdPlugin->getPubIdAssignFile()}
                {include file="$pubIdAssignFile" pubObject=$pubObject pubObjectType=$pubObjectType}
            {/if}
        {/foreach}
    {/fbvFormArea}
{/if}
{fbvFormArea id="confirmationText"}
    <p>{$confirmationText}</p>
{/fbvFormArea}
{fbvFormButtons id="assignPublicIdentifierForm" submitText="common.ok"}
</form>