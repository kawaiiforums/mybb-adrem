<img align="left" src="https://raw.githubusercontent.com/kawaiiforums/mybb-adrem/master/.github/logomark-400h.png" height="80">

# Ad Rem

A MyBB extension that executes moderation actions based on content quality.

Inspects discovered content by feeding Content Entity data to modular Assessments to conditionally trigger Actions, resulting in automated content moderation.

### Features
- #### Flexible Rules
  Actions are executed automatically, depending on the JSON Ruleset defined and validated on the plugin's Settings page in the Administration Control Panel.
- #### Inspection & Assessment Logs and Statuses
  Icons indicating latest Inspection status lead to _Inspection Logs_ in the Moderator Control Panel.
  
  Plugin-generated logs are pruned according to MyBB's moderator log retention period ([`$config['log_pruning']['mod_logs']`](https://docs.mybb.com/1.8/administration/configuration-file/)).

### Dependencies
- MyBB 1.8.x
- https://github.com/frostschutz/MyBB-PluginLibrary
- PHP >= 7.1

---

### Ruleset
The Ad Rem Ruleset JSON object consists of properties with unique Content Type names as keys and arrays of attached Conditionals as values (`{Object<string, Conditional[]>} ruleset`).

A **Conditional** consists of the following properties:
- `{string[]} [events]` - event names the Conditional applies to (or all events if not specified),
- `{RuleGroup[]} rules` - an array containing the root Rule Group,
- `{string[]} actions` - an array containing the names of Actions to run on positive result.

A **Rule Group** is a truth function that consists of a property with a Group Operator as key and an array of linked Rules and/or Rule Groups as value (`{Object<string, Array<Rule|RuleGroup>>}`).

Supported Group Operators:
- `any` (logical *OR* equivalent),
- `all` (logical *AND* equivalent).

A **Rule** is a true/false logical condition and is represented as an array that consists of the following elements:
1. `{string}` an Assessment name and an Attribute it provides, separated with a colon (`:`),
   - if preceded with `Δ`, the change in value between two revisions of data is used (the value in revision named `previous` is subtracted from the value in revision named `current`)
2. `{string}` a Rule Comparison Operator,
3. `{string}` a reference value.

Supported Rule Comparison Operators:
- `<` (less than),
- `<=` (less than or equal to),
- `>` (greater than),
- `>=` (greater than or equal to),
- `=` (equal to),
- `!=` (not equal to).

An **Action** supported by the inspected Content Type, or other Content Types that accept context passed from it (e.g. the author of inspected post), may be triggered on positive evaluation of the root Rule Group. Names of Actions triggered in related Content Types are preceded by the Content Type name and separated with a colon (e.g. `user:warn`).

The ACP's Ruleset verification validates the syntax and checks availability of used Content Types, Assessments, Attributes, and Actions.

**Example** &mdash; default Ruleset:
```json
{
    "post": [
        {
            "rules": [
                {"any": [
                    ["core:wordfilterCount", ">=", "3"]
                ]}
            ],
            "actions": ["softDelete"]
        }
    ]
}
```
**Example** - using Conversation AI's [Perspective API](https://github.com/conversationai/perspectiveapi), and checking for added links on post edit:
```json
{
    "post": [
        {
            "rules": [
                {"any": [
                    ["perspective:INCOHERENT", ">=", "0.75"],
                    ["perspective:SPAM", ">=", "0.75"],
                    ["perspective:UNSUBSTANTIAL", ">=", "0.75"],
                    {"all": [
                        ["perspective:FLIRTATION", ">=", "0.75"],
                        ["perspective:SEXUALLY_EXPLICIT", ">=", "0.75"]
                    ]}
                ]}
            ],
            "actions": ["report"]
        },
        {
            "rules": [
                {"any": [
                    ["perspective:SEVERE_TOXICITY", ">=", "0.4"],
                    ["perspective:THREAT", ">=", "0.4"]
                ]}
            ],
            "actions": ["report", "softDelete", "user:moderatePosts"]
        },

        {
            "events": ["update"],
            "rules": [
                {"any": [
                    ["Δcore:mycodeLinkCount", ">=", "1"]
                ]}
            ],
            "actions": ["report"]
        }
    ]
}
```

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
