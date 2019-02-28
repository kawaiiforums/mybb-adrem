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
				<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
					<tr>
						<td class="thead" colspan="4"><strong>{$title}</strong></td>
					</tr>
					<tr>
						<td class="tcat" width="25%" align="center">
							<span class="smalltext"><strong>{$lang->adrem_content_entity}</strong></span>
						</td>
						<td class="tcat" width="25%" align="center">
							<span class="smalltext"><strong>{$listManager->link('date_completed', $lang->adrem_date_completed)}</strong></span>
						</td>
						<td class="tcat" width="25%" align="center">
							<span class="smalltext"><strong>{$lang->adrem_inspection_actions}</strong></span>
						</td>
						<td class="tcat" width="25%" align="center">
							<span class="smalltext"><strong>{$lang->controls}</strong></span>
						</td>
					</tr>
					{$results}
				</table>
				{$resultspages}
				{$request}
			</td>
		</tr>
	</table>
{$footer}
</body>
</html>