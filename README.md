# Ad Rem

A MyBB extension that executes moderation actions based on content quality.

Inspects discovered content by feeding Content Entity data to modular Assessments providing Attribute values used to conditionally trigger actions, resulting in automated content moderation.

### Features
- #### Flexible Rules
  Actions are executed automatically, depending on the Ruleset defined and validated in the Administration Control Panel.
- #### Inspection & Assessment Logs and Statuses
  Icons indicating Inspection statuses (_not inspected_, _inspected_, or _actions taken_) lead to _Inspection Logs_ in the Moderator Control Panel.

### Dependencies
- MyBB 1.8.x
- https://github.com/frostschutz/MyBB-PluginLibrary
- PHP >= 7.1

### Ruleset
The Ad Rem Ruleset, defined and verified on plugin's Settings page, consists of { `rules` → `actions` } Conditionals for each Content Type in the JSON format:

```json
{
    "CONTENT TYPE": [
        {
            "rules": [],
            "actions": []
        },
        {
            "rules": [],
            "actions": []
        }
    ],
    "CONTENT TYPE": [
        {
            "rules": [],
            "actions": []
        }
    ]
}
```

The `rules` array consists of one root Rule Group, where multiple Rules and Groups can be nested:
```json
{"RULE OPERATOR": [
    ["ASSESSMENT:ATTRIBUTE", "COMPARISON OPERATOR=", "REFERENCE VALUE"],
    {"RULE OPERATOR": [
        ["ASSESSMENT:ATTRIBUTE", "COMPARISON OPERATOR", "REFERENCE VALUE"],
        ["ASSESSMENT:ATTRIBUTE", "COMPARISON OPERATOR", "REFERENCE VALUE"]
    ]}
]}
```

A Rule is represented as an array indicating an Assessment name and an Attribute it provides, a comparison operator, and a reference value, resulting in a true-false logical condition.

**Supported rule operators**:
- `<`,
- `<=`
- `>`,
- `>=`,
- `=`,
- `!=`.

Rule Groups can be used to evaluate the result of multiple Rules or nested Rule Groups.

**Supported group operators**:
- `any` (*OR* equivalent),
- `all` (*AND* equivalent).

Positive evaluation of the root Rule Group results in triggering `actions` supported for given Content Type specified in the Conditional (default) or other Content Types that support context passed from it:
```json
["ACTION NAME", "CONTENT TYPE:ACTION NAME"]
```


The ACP's Ruleset verification validates the syntax and checks availability of used Content Types, Assessments, Attributes, and Actions.

**Example** &mdash; default Ruleset:
```json
{
    "post": [
        {
            "rules": [
                {"any": [
                    ["core:wordfilterMatches", ">=", "3"]
                ]}
            ],
            "actions": ["softDelete"]
        }
    ]
}
```
**Example** - Conversation AI's [Perspective API](https://github.com/conversationai/perspectiveapi):
```json
{
    "post": [
        {
            "rules": [
                {"any": [
                    ["perspective:SEVERE_TOXICITY", ">=", "0.4"],
                    ["perspective:THREAT", ">=", "0.4"]
                ]}
            ],
            "actions": ["softDelete", "user:moderatePosts"]
        },
        {
            "rules": [
                {"any": [
                    ["perspective:INCOHERENT", ">=", "0.75"],
                    ["perspective:SPAM", ">=", "0.75"],
                    ["perspective:UNSUBSTANTIAL", ">=", "0.75"]
                ]}
            ],
            "actions": ["report"]
        }
    ]
}
```

### Data Flow
1. *Ad Rem* receives the discovered Content Entity (usually through plugin hooks),
2. An Inspection is run:
   1. assessments and their Attributes required to resolve the root Rule Group are inferred,
   2. selected Assessments are run by passing the Content Entity and a list of requested Attributes,
   3. the root Rule Group is resolved using collected Attribute values.
3. Related Content Entities are loaded by context of the initial Content Entity.
4. Actions for requested Content Entities are triggered.

### Plugin Management Events
- **Install:**
  - Database structure created
  - Cache entries created
- **Uninstall:**
  - Database structure & data deleted
  - Settings deleted
  - Cache entries removed
- **Activate:**
  - Modules detected
  - Settings populated/updated
  - Templates & stylesheets inserted/altered
- **Deactivate:**
  - Templates & stylesheets removed/restored

### Development Mode
The plugin can operate in development mode, where plugin templates are being fetched directly from the `templates/` directory - set `adrem\DEVELOPMENT_MODE` to `true` in `inc/plugins/adrem.php`.
