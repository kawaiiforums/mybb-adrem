<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->adrem_inspection_details}</title>
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
						<td class="thead" colspan="2"><strong>{$lang->adrem_inspection_details}</strong></td>
					</tr>
					<tr>
						<td class="tcat" colspan="2">
							<span class="smalltext"><strong>{$lang->adrem_inspection_details_general}</strong></span>
						</td>
					</tr>
					<tr>
						<td class="trow1" width="20%">
							<strong>{$lang->adrem_content_entity}</strong>
						</td>
						<td class="trow1" width="80%">
							<a href="{$entityUrl}"><code>{$contentType} #{$entityId}</code></a>
						</td>
					</tr>
					<tr>
						<td class="trow1" width="20%">
							<strong>{$lang->adrem_date_completed}</strong>
						</td>
						<td class="trow1" width="80%">
							{$dateCompleted}
						</td>
					</tr>
					<tr>
						<td class="trow1" width="20%">
							<strong>{$lang->adrem_inspection_actions}</strong>
						</td>
						<td class="trow1" width="80%">
							{$actions}
						</td>
					</tr>
					{$contentEntityData}
				</table>
				{$inspectionAssessments}
			</td>
		</tr>
	</table>
{$footer}
</body>
</html>