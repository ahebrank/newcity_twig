# NewCity Twig Extensions

A Drupal 8 module to provide some useful Twig extensions and functions

Currently includes:

## Extensions

### Render helpers

- `resize(style)`: resize an image with an image style; returns image render array
- `flattenfield`: traverse and flatten a render array
- `smarttrim(wordCount)`: a smarter truncation function (Example: `content.field_text|render|striptags|smarttrim(n)`)
- `alias`: get path alias for an entity

### Project-specific but potentially useful

- `multilinesuperhead`: Break a multiline heading by wrapping all words except the last in `<small>`

### Debug helpers 

- `nocomment` : removes HTML comments from markup
- `firstlevel` : debug dump only the top level of a render array

## Functions

- `uniqid()` : generate a unique ID with PHP's `uniqid()`