<html>
<head>
<title>{$mybb->settings['bbname']} - {$title}</title>
{$headerinclude}
</head>
<body>
    {$header}
    <table width="100%" border="0" align="center">
        <tr>
            {$modcp_nav}
            <td valign="top">
                <form action="" method="post">
                    <table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
                        <tr>
                            <td class="thead" colspan="3"><strong>{$title}</strong></td>
                        </tr>
                        <tr>
                            <td class="tcat">
                                <span class="smalltext"><strong>{$lang->adrem_assessment_attribute_name}</strong></span>
                            </td>
                            <td class="tcat">
                                <span class="smalltext"><strong>{$lang->adrem_assessment_attribute_value}</strong></span>
                            </td>
                            <td class="tcat">
                                <span class="smalltext"><strong>{$lang->adrem_assessment_attribute_suggested_value}</strong></span>
                            </td>
                        </tr>
                        {$rows}
                    </table>
                    <br />
                    <div align="center">
                        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                        <input type="submit" class="button" name="submit" value="{$lang->adrem_assessment_suggest_submit}" />
                    </div>
                </form>
            </td>
        </tr>
    </table>
{$footer}
</body>
</html>