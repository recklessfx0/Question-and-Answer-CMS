# Discussion UI audit

Scope covers the public and signed-in discussion, including moderation. The local launch checklist question contains an accepted answer, several additional answers, multiple clarification roots, sibling replies, and a six-level reply chain.

| Section | Finding | Change |
| --- | --- | --- |
| Question header | The question and its first clarification used the same three-dot control despite acting on different records. | The question has one three-dot menu. Clarifications use a labeled Manage disclosure. Each answer retains its own menu for answer actions. |
| Author and post body | Deep replies repeated parent excerpts and devoted increasing horizontal space to decoration. | Each reply identifies its parent with a direct link and a thin indent. Indents stop increasing after two levels on narrow phones and three on wider layouts. |
| Missing parent | A visible reply could disappear when its parent was no longer part of the visible thread. | Visible replies with unavailable parents appear at the root with an explanation. |
| Votes and reactions | Narrow reply rows can be crowded by long text, scores, and interaction labels. | Clarification text wraps, action rows reflow, and primary post votes and reactions retain their existing grouped controls. |
| Clarification lists | Long threads push answers and composition out of the initial reading path. | Question and answer threads can collapse independently. Comment fragment links reopen a collapsed thread. |
| Reply composition | “Quote” displayed quoted text but submitted a root comment because the parent ID was reset to zero. | Reply saves the selected parent in the correct composer and keeps the quote. Clarification and video fields have accessible names. |
| Moderator tools | Repeated icon-only controls made the affected post unclear. | Reply moderation has visible Manage text. The action panel expands inline on phones. Edit and delete remain scoped to the individual clarification. |
| Answer composer and related content | A long discussion can make the response form hard to find. | Existing answer links, composer, media controls, and related questions remain in the reading flow. Collapsible threads shorten that path when needed. |
| HTML and Markdown retrieval | Flattened text did not identify the immediate parent of a reply. | HTML and Markdown both expose stable links to parent clarifications. |

## Verification

Edge checks at 320, 390, 768, and 1440 px covered the six-level thread, branching replies, horizontal overflow, and direct links reopening collapsed threads. A signed-in browser check confirmed one question menu, selected a parent using Reply, submitted a demo clarification, and landed at the new reply’s anchor. The sample now has five answers and 19 clarifications.
