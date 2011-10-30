PYTHON=		python

VERS=		1.0.0
DISTNAME=	azlink-tinywidget-$(VERS)
DIST=		$(DISTNAME).zip
DIRS=		json
DISTFILES=	api.php config.php.example \
		tinywidget.min.js.in tinywidget.min.js \
		styles.css index.html work/.htaccess

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
	rm -rf $(DIST) $(DISTNAME)
	mkdir $(DISTNAME)
	umask 0; tar -cf - $(DISTFILES) | (cd $(DISTNAME); tar -xf -)
	cd $(DISTNAME); mkdir $(DIRS); chmod 777 $(DIRS)
	zip -qX -r $(DIST) $(DISTNAME)/*
	rm -rf $(DISTNAME)

distclean:
	rm -rf $(DIST) $(DIRS) tinywidget.min.js
