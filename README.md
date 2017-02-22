# GeographicalCollocatesTool
Geographical Collocates Tool built upon Corpus Workbench (CWB) for the Spatial Humanities project (http://www.lancaster.ac.uk/fass/projects/spatialhum.wordpress/).

This is a web application that allows for searches of placename mentions that collocate with a corpus query. It is built upon, and relies on, a Corpus Workbench install, with CQP queries ran on a corpus with placename mentions marked up in XML.

Included are:

* dbcreate.sql - to create the datbase used by the web application.
* newcorpus - facility for adding a new corpus to the database.
* includes - backend methods for running corpus and database queries.
* www - frontend files for forms for running queries and displaying results (a series of created files for download).

Missing are the user account creation and management functionality.
