PYTHON=		python

VERS=		1.0.4
DISTNAME=	azlink-tinywidget-$(VERS)
DIST=		$(DISTNAME).zip
DISTFILES=	api.php config.php.defaults tinywidget.min.js \
		styles.css index.html json/.empty work/.htaccess

.PHONY: all clean dist distclean

all: README.rdoc tinywidget.min.js

README.rdoc: README.rdoc.in Makefile
	sed -e 's/@VERS@/$(VERS)/g' README.rdoc.in > README.rdoc

tinywidget.min.js: compile.py tinywidget.js
	$(PYTHON) compile.py tinywidget.js $@

clean:
	rm -f tinywidget.min.js
	rm -rf $(DIRS)

dist: all
	rm -rf $(DIST) $(DISTNAME)
	mkdir $(DISTNAME)
	umask 0; tar -cf - $(DISTFILES) | (cd $(DISTNAME); tar -xf -)
	cd $(DISTNAME); chmod 777 json work
	zip -qX -r $(DIST) $(DISTNAME)/*
	rm -rf $(DISTNAME)

distclean: clean
	rm -rf $(DIST)
