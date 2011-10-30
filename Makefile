PYTHON=		python

VERS=		1.0.0
DISTNAME=	azlink-tinywidget-$(VERS)
DIST=		$(DISTNAME).zip
DIRS=		json
DISTFILES=	api.php config.php.example tinywidget.min.js \
		styles.css index.html work/.htaccess

.PHONY: all clean dist distclean

all: $(DIRS) tinywidget.min.js
	@chmod 777 $(DIRS)

$(DIRS):
	mkdir -p $@

tinywidget.min.js: compile.py tinywidget.js
	$(PYTHON) compile.py tinywidget.js $@

clean:
	rm -f tinywidget.min.js
	rm -rf $(DIRS)

dist: all
	rm -rf $(DIST) $(DISTNAME)
	mkdir $(DISTNAME)
	umask 0; tar -cf - $(DISTFILES) | (cd $(DISTNAME); tar -xf -)
	cd $(DISTNAME); mkdir $(DIRS); chmod 777 $(DIRS)
	zip -qX -r $(DIST) $(DISTNAME)/*
	rm -rf $(DISTNAME)

distclean: clean
	rm -rf $(DIST)
