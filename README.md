# AIQuiz #
AIQuiz is a Moodle plugin that generates quizzes dynamically using artificial intelligence. It leverages the OpenAI API to create questions and answers based on syllabus PDFs or other content, allowing educators to effortlessly build personalized, AI-powered assessments. The plugin also supports PDF parsing and merging, enabling easy extraction of text and integration of multiple documents.

Key features include:

- Automatic question generation from uploaded syllabus PDFs.

- Support for multiple question types with AI-generated feedback.

- Integration with OpenAI’s GPT models via the official PHP client.

- PDF manipulation using FPDF, FPDI, and PDFParser libraries.

- Easy setup and configuration with Moodle’s standard plugin interface.

## Requirements


1. **PHP 8.1 or higher**
2. **Composer**
   - Download and install from [getcomposer.org](https://getcomposer.org).
3. **Ghostscript**
   - Download the latest Windows build from the official site:  
     https://www.ghostscript.com/releases/gsdnld.html
   - Run the installer.
   - Add the installation directory to your Windows **PATH**.
   - In that directory, locate `gswin64c.exe` (or `gswin64.exe`) and rename it to **`gs.exe`**.
4. **PHP Libraries (install via Composer)**
   - **FPDF** (PDF generation)
     ```bash
     composer require setasign/fpdf
     ```  
   - **FPDI** (PDF page import extension for FPDF)
     ```bash
     composer require setasign/fpdi
     ```  
   - **PDFParser** (extract text from existing PDFs)
     ```bash
     composer require smalot/pdfparser
     ```  
   - **openai-php/client** (OpenAI API client)
     ```bash
     composer require openai-php/client
     ```  
5. **OpenAI API Key**
   - Set the OpenAI API key from the the plugins settings page.
   - You can get your API key from the [OpenAI website](https://platform.openai.com).


****
## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/mod/aiquiz

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.
