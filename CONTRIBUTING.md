For All Potential Contributors
====
This repository contains the code and development history of the main web site for the WikiPathways project:
[wikipathways.org](http://wikipathways.org). Built upon MediaWiki, 
the site includes numerous custom extensions, javascript, skins and hacks.

### Contributing open source code
If you are interested in contributing a minor correction or compatibility bug fix, feel free to send a Pull Request.
For any larger features, please [Post an Issue](https://github.com/wikipathways/wikipathways.org/issues) before starting work. 
It is important that the implementation details are clear and aligned with our [Roadmap](ROADMAP.md) to ensure that effort isn't wasted.
Here are some overview resources:
* Overview of [WikiPathways Architecture](https://drive.google.com/file/d/0B9t1PdWt7kEUcERPSVRCN0lrZGc/view?usp=sharing)
* [Open issues for new contributors](https://github.com/nrnb/GoogleSummerOfCode/issues?q=is%3Aissue+is%3Aopen+label%3AWikiPathways)
* Repositories:
  * [wikipathways.org repo](https://github.com/wikipathways/wikipathways.org) - (PHP) The MediaWiki and extensions underlying the main website at https://wikipathways.org
  * [pvjs](https://github.com/wikipathways/pvjs) - (JS) The interactive pathway viewer
  * [Other WikiPathways repos](https://github.com/wikipathways) - (Java, JS, PHP, Python) Diverse projects related to WikiPathways.
  * [PathVisio projects](https://github.com/pathvisio) - (Java, Python) The pathway diagram drawing tool. Also a pathway analysis tool.
  * [BridgeDb projects](https://github.com/bridgedb) - (Java, JS) The underlying gene, protein and metabolite identifier resources. 

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

### Also check out
* The project [Roadmap](ROADMAP.md)
* Our community [Code of Conduct](CODEOFCONDUCT.md)
* Our organization [SOP](SOP.md)

### Installation

We do not recommend attempting to install this site code as-is. There are many parts and services required that are not included here. Contact one of the [architects](https://www.wikipathways.org/index.php/WikiPathways:Team#Architects) for more details.

If you do attempt to install, note instructions in the README.md files in each subdirectory of wpi/extensions/, e.g., GPMLConverter and Pathways.

### Contributing pathway content
If you are interested in adding or editing pathway diagrams, check out these resources:
* [Help Contents](https://www.wikipathways.org/index.php/Help:Contents)
* [New Contributor Quickstart](https://www.wikipathways.org/index.php/Help:New_Contributor_Quickstart)
* [Instructions for WikiPathways Authors](http://wikipathways.org/img_auth.php/d/d0/Instructions_for_WikiPathways_Authors.pdf)
* [WikiPathways Academy Training](http://academy.wikipathways.org/)
