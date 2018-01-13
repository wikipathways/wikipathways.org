Current repository for the WikiPathways web site
====
This repository contains the code and development history of the main web site for the WikiPathways project:
[wikipathways.org](http://wikipathways.org). Built upon MediaWiki, 
the site includes numerous custom extensions, javascript, skins and hacks.

Developers
---
If you are in the development team, you should have write access to this repository. If you are an external developer, 
feel free to clone and make pull requests.

We have a development instance of WikiPathways always available at [dev.wikipathways.org](http://dev.wikipathways.org) that 
is running the ```dev``` branch of this repo.  If you are in the development team, feel free to switch this instance to ```feature*``` branches
temporarily for testing, but please restore the instance to the ```dev``` branch when you are done.

**Branching strategy**

When making decisions about how and when to branch, consider the following points:
* The ```master``` branch should always reflect the current deployable state of the site.
* Between releases, most development can be pushed directly to the ```dev``` branch.
* For major features being developed over a week or longer, consider creating a ```feature*``` branch off of ```dev``` to be merged back 
into ```dev``` when complete.
* Provide an informative name for your ```feature*``` branch, e.g., ```feature-new-search```.
* At release time, we will merge ```dev``` back into ```master``` and provide a milestone number in the comment, e.g., *Milestone 45*. 
It should be easy to track the release history via the ```master``` branch log.
* After releases, we may deleted ```feature*``` branches, but the ```dev``` branch will live on... forever!

```
              Milstone 45 (minor release)            Milestone 46 (major feature release)
                      |                                       |
master -------------------------------------------------------------->
          \         /                                       /
           dev ------------------------------------------------------>
                           \                      /
                            feature-new-search ---
```

Installation
---
We do not recommend attempting to install this site code as-is. There are many parts and services required that are not included here. Contact one of the [architects](https://www.wikipathways.org/index.php/WikiPathways:Team#Architects) for more details.

If you do attempt to install, note instructions in the README.md files in each subdirectory of wpi/extensions/, e.g., GPMLConverter and Pathways.

---
Old Repo: http://svn.bigcat.unimaas.nl/wikipathways/
