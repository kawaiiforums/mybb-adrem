<tr>
    <td class="tcat" colspan="2">
        <span class="smalltext"><code>{$name}</code></span>
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
        <strong>{$lang->adrem_duration}</strong>
    </td>
    <td class="trow1" width="80%">
        {$duration}
    </td>
    {$attributeValueRows}
    <tr>
        <td class="trow1" width="20%">
            <strong>{$lang->adrem_assesment_result_data}</strong>
        </td>
        <td class="trow1" width="80%">
            <pre><code>{$resultData}</code></pre>
        </td>
    </tr>
    {$controls}
</tr>