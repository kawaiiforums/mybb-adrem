<tr>
    <td class="tcat" colspan="2">
        <span class="smalltext"><code>{$name}</code></span>
    </td>
</tr>
<tr>
    <td class="trow1" width="20%">
        <strong>{$lang->adrem_inspection_date_completed}</strong>
    </td>
    <td class="trow1" width="80%">
        {$dateCompleted}
    </td>
</tr>
<tr>
    <td class="trow1" width="20%">
        <strong>{$lang->adrem_inspection_duration}</strong>
    </td>
    <td class="trow1" width="80%">
        {$duration}
    </td>
    <tr>
        <td class="trow1" width="20%">
            <strong>{$lang->adrem_inspection_assessment_attribute_values}</strong>
        </td>
        <td class="trow1" width="80%">
            <ul>
               {$attributeValues}
            </ul>
        </td>
    </tr>
    <tr>
        <td class="trow1" width="20%">
            <strong>{$lang->adrem_inspection_assesment_result_data}</strong>
        </td>
        <td class="trow1" width="80%">
            <pre><code>{$resultData}</code></pre>
        </td>
    </tr>
</tr>