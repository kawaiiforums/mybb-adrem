$(function () {
    if (document.getElementById("setting_adrem_ruleset") !== null) {
        var editor = CodeMirror.fromTextArea(document.getElementById("setting_adrem_ruleset"), {
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            indentUnit: 4,
            theme: "mybb",
        });

        var html = "<div id=\"adrem_validation_results\"></div>";

        $("#row_setting_adrem_ruleset td").append(html);

        var updateResults = function () {
            $.post("index.php?module=config-adrem_ruleset_validation", {
                value: editor.getValue(),
            }, function (data) {
                var content = '';

                if (data.errors.length !== 0) {
                    content += '<strong>' + lang.adrem_validation_errors + '</strong>';
                    content += '<ul>';

                    for (var i in data.errors) {
                        content += '<li>' + data.errors[i] + '</li>';
                    }

                    content += '</ul>';
                }

                if (data.warnings.length !== 0) {
                    content += '<strong>' + lang.adrem_validation_warnings + '</strong>';
                    content += '<ul>';

                    for (var i in data.warnings) {
                        content += '<li>' + data.warnings[i] + '</li>';
                    }

                    content += '</ul>';
                }

                $("#adrem_validation_results").html(content);
            });
        };

        var updateTimeout = null;

        var updateResultsDelayed = function () {
            clearTimeout(updateTimeout);
            updateTimeout = setTimeout(updateResults, 500);
        };

        updateResults();

        editor.on("change", updateResultsDelayed);
        editor.on("blur", updateResults);
    }
});
