<?php

namespace adrem;

// inspections
function getInspectionEntry(int $id): ?array
{
    global $db;

    $query = $db->simple_select('adrem_inspections', '*', 'id = ' . (int)$id);

    if ($db->num_rows($query) == 1) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}

function getCompletedInspectionEntriesByContentTypeEntityId(string $contentType, array $entityIds): array
{
    global $db;

    $data = [];

    $entityIdsCsv = implode(
        ',',
        array_map('intval', $entityIds)
    );

    $query = $db->query("
        SELECT
            *
        FROM
            " . $db->table_prefix . "adrem_inspections
        WHERE
            id IN (
                SELECT
                    MAX(id)
                FROM
                    " . $db->table_prefix . "adrem_inspections
                WHERE
                    content_type = '" . $db->escape_string($contentType) . "' AND
                    content_entity_id IN (" . $entityIdsCsv . ") AND
                    completed = 1
                GROUP BY content_entity_id
            )
    ");

    while ($row = $db->fetch_array($query)) {
        $data[$row['content_entity_id']] = $row;
    }

    return $data;
}

function getCompletedInspectionEntries(?string $contentType = null, ?int $entityId = null, ?string $parameters = null)
{
    global $db;

    $where = 'completed = 1';

    if ($contentType && $entityId) {
        $where .= " AND content_type = '" . $db->escape_string($contentType) . "' AND content_entity_id = " . (int)$entityId;
    }

    return $db->query("
        SELECT
            *
        FROM
            " . $db->table_prefix . "adrem_inspections
        WHERE
            {$where}
        {$parameters}
    ");
}

function getCompletedInspectionEntriesCount(?string $contentType = null, ?int $entityId = null): int
{
    global $db;

    $where = 'completed = 1';

    if ($contentType && $entityId) {
        $where .= " AND content_type = '" . $db->escape_string($contentType) . "' AND content_entity_id = " . (int)$entityId;
    }

    return $db->fetch_field(
        $db->query("
            SELECT
                COUNT(id) AS n
            FROM
                " . $db->table_prefix . "adrem_inspections
            WHERE
                {$where}
        "),
        'n'
    );
}

// assessments
function getInspectionAssessmentEntries(int $inspectionId)
{
    global $db;

    $query = $db->simple_select('adrem_assessments', '*', 'inspection_id = ' . (int)$inspectionId);

    return $query;
}
