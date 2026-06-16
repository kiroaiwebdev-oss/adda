# Code-block rendering convention (prevents the "black background" bug)

Lesson/topic content is stored as HTML and often contains code blocks shaped
like `<pre><code>...</code></pre>`.

Many editors/previews style code like this:

```css
.someScope code { background: #f3f4f6; }      /* light inline-code chip */
.someScope pre  { background: #1f2937; color: #f9fafb; }  /* dark code block */
```

The trap: a `<code>` **inside** a dark `<pre>` inherits the light `color`
from `pre` while also getting the light `code` background → light-on-light
text that renders as invisible white bars on a dark block. This is the
"black background / code not visible" issue.

## Rule

Any place that renders saved lesson/course/internship content (admin builders,
content editors, preview/view pages, learner players) MUST include a reset for
code nested inside a code block:

```css
<scope> pre code {
    background: transparent;
    color: #f9fafb;     /* or `inherit` / match the <pre> text color */
    padding: 0;
    border-radius: 0;
    font-size: inherit;
    white-space: pre;
}
```

Also give standalone inline `code` an explicit dark `color` (e.g. `#111827`)
so it is never light-on-light.

## Files that currently follow this (keep them in sync)

- app/views/admin/courses/builder.php
- app/views/admin/courses/view.php
- app/views/admin/internships/builder.php
- app/views/admin/internships/views.php
- app/views/learner/course-player.php
- app/views/learner/internship-player.php
- second/course_content_editor.php
- second/internship_builder.php

When adding a new content editor/preview, add the same `pre code` reset.
