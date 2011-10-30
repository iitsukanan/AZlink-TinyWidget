#!/usr/bin/env python
# Grab from http://code.google.com/intl/en/closure/compiler/docs/api-tutorial1.html

import httplib, urllib, sys

if len(sys.argv) != 3:
    print >>sys.stderr, "Usage: %s SRC DEST" % sys.argv[0]
    sys.exit(1)

fh = open(sys.argv[1], 'rb')
js_code = fh.read()
fh.close()

# Define the parameters for the POST request and encode them in
# a URL-safe format.

params = urllib.urlencode([
    ('js_code', js_code),
    ('compilation_level', 'SIMPLE_OPTIMIZATIONS'),
    ('output_format', 'text'),
    ('output_info', 'compiled_code'),
])

# Always use the following value for the Content-type header.
headers = { "Content-type": "application/x-www-form-urlencoded" }
conn = httplib.HTTPConnection('closure-compiler.appspot.com')
conn.request('POST', '/compile', params, headers)
response = conn.getresponse()
data = response.read()
conn.close

fh = open(sys.argv[2], 'wb')
print >> fh, '// This file is licensed under the MIT license'
print >> fh, '// https://github.com/sakuratan/AZlink-TinyWidget'
fh.write(data)
fh.close()
