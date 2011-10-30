VERS=	1.0.0
DIST=	azlink-tinywidget-$(VERS).zip
DIRS=	json work

.PHONY: all dist distclean

all: $(DIRS) tinywidget.min.js
	@chmod 777 $(DIRS)
	@chmod 666 tinywidget.min.js

$(DIRS):
	mkdir -p $@

tinywidget.min.js: api.php tinywidget.js
	php api.php -b'false'
	chmod 666 $@

dist: all
	zip -X $(DIST) api.php index.html tinywidget.js tinywidget.min.js $(DIRS)

distclean:
	rm -rf $(DIST) $(DIRS) tinywidget.min.js
