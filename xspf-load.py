#!/bin/python
# -*- encoding: utf-8 ~*~
from BeautifulSoup import BeautifulSoup, NavigableString
import urllib
import threading
from threading import Thread
from Queue import Queue
import sys


if len(sys.argv) < 2:
    print "usage command: <filename.xspf>"
    exit()

xmlFilename = sys.argv[1]

xmldata = ''
try:
    with open(xmlFilename, 'r') as content_file:
        xmldata = content_file.read()
except:
    print "error while load file"
    exit()
	 
soup = BeautifulSoup(xmldata)

queue = Queue();

exitapp = False

class ThreadUrl(Thread):
    """Threaded Url Grab"""
    def __init__(self, queue):
        threading.Thread.__init__(self)
        self.queue = queue

    def run(self):
        while not exitapp:
            #grabs host from queue
            element = self.queue.get()
            url = element.get('url')
            name = element.get('name')

            print "url " + url
            print "name " + name

            f = urllib.urlopen(url)
            fh = open(name, 'wb')
            fh.write(f.read())
            fh.close()

            #signals to queue job is done
            self.queue.task_done()




threads = []
for i in range(10):
    t = ThreadUrl(queue)
    threads.append(t);
    t.setDaemon(True)
    t.start()



for track in soup.findAll('track'):
    name = track.title.text
    url = track.location.text
    queue.put({'name': name, 'url':url})


queue.join()
