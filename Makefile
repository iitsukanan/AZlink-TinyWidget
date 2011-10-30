PYTHON=	python

VERS=	1.0.0
DIST=	azlink-tinywidget-$(VERS).zip
DIRS=	json work

.PHONY: all dist distclean

all: $(DIRS) tinywidget.min.js
	@chmod 777 $(DIRS)

$(DIRS):
	mkdir -p $@

tinywidget.min.js: tinywidget.min.js.in
	cp tinywidget.min.js.in tinywidget.min.js
	chmod 666 $@

tinywidget.min.js.in: api.php tinywidget.js
	$(PYTHON) compile.py tinywidget.js $@

dist: all
	zip -X $(DIST) api.php index.html tinywidget.js tinywidget.min.js $(DIRS)

distclean:
	rm -rf $(DIST) $(DIRS) tinywidget.min.js
