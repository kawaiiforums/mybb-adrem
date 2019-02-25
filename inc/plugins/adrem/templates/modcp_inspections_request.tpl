<br />
<form action="modcp.php?action=content_inspections" method="post">
    <div align="center">
        <input type="hidden" name="discover" value="1" />
        <input type="hidden" name="type" value="{$contentType}" />
        <input type="hidden" name="entity_id" value="{$contentEntityId}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <input type="submit" class="button" name="submit" value="{$lang->adrem_request_inspection}" />
    </div>
</form>