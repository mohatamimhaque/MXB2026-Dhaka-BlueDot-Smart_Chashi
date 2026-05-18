@echo off
setlocal EnableDelayedExpansion

set "BASE_DIR=%~dp0"
set "VENV_DIR=%BASE_DIR%venv"
set "PYTHON_EXE=%VENV_DIR%\Scripts\python.exe"
set "REQ_FILE=%BASE_DIR%requirements.txt"
set "APP_SCRIPT=%BASE_DIR%app.py"
set "APP_PORT=8080"

set "PYTHONIOENCODING=utf-8"
chcp 65001 >nul 2>&1

title Smart Chashi - Disease Detection System
cls

echo.
echo  ================================================================
echo   Smart Chashi  ^|  AI Disease Detection System
echo  ================================================================
echo   Web App       ^|  http://localhost:%APP_PORT%
echo  ================================================================
echo.

REM --- Step 1: Check Python ---
echo  [1/4] Checking Python installation...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [ERROR] Python is not installed or not in PATH.
    echo.
    echo  Install Python 3.10+ from https://www.python.org/downloads/
    echo  Check "Add Python to PATH" during installation.
    echo.
    pause
    exit /b 1
)
for /f "tokens=2 delims= " %%v in ('python --version 2^>^&1') do set PY_VER=%%v
echo         Python %PY_VER% found.

REM --- Step 2: Create virtual environment ---
echo  [2/4] Checking virtual environment...
if not exist "%PYTHON_EXE%" (
    echo         Creating virtual environment - please wait...
    python -m venv "%VENV_DIR%"
    if %errorlevel% neq 0 (
        echo.
        echo  [ERROR] Failed to create virtual environment.
        echo  Try: python -m venv venv
        echo.
        pause
        exit /b 1
    )
    echo         Virtual environment created.
) else (
    echo         Virtual environment OK.
)

REM --- Step 3: Auto-install dependencies ---
echo  [3/4] Checking dependencies...
"%PYTHON_EXE%" -c "import flask, torch, PIL, transformers, numpy" >nul 2>&1
if %errorlevel% neq 0 (
    echo         Missing packages detected. Installing now...
    echo         This may take 3-10 minutes on first run - please wait.
    echo.
    "%PYTHON_EXE%" -m pip install --upgrade pip --quiet --no-warn-script-location
    "%PYTHON_EXE%" -m pip install -r "%REQ_FILE%" --quiet --no-warn-script-location
    if %errorlevel% neq 0 (
        echo.
        echo  [ERROR] Package installation failed.
        echo  Try: venv\Scripts\activate  then  pip install -r requirements.txt
        echo.
        pause
        exit /b 1
    )
    "%PYTHON_EXE%" -c "import transformers" >nul 2>&1
    if %errorlevel% neq 0 (
        echo         Installing transformers...
        "%PYTHON_EXE%" -m pip install transformers --quiet --no-warn-script-location
    )
    echo.
    echo         All dependencies installed.
) else (
    echo         All dependencies OK.
)

REM --- Step 4: Start App (single port) ---
echo  [4/4] Starting application on port %APP_PORT%...
echo.
echo  ================================================================
echo   RUNNING
echo   Web App + ML API  ^|  http://localhost:%APP_PORT%
echo   /detect           ^|  Plant detection endpoint
echo   /api/analyze      ^|  Disease analysis endpoint
echo   /health           ^|  Health check
echo  ================================================================
echo   Press Ctrl+C to stop.
echo  ================================================================
echo.

timeout /t 2 /nobreak >nul 2>&1
start "" "http://localhost:%APP_PORT%"

"%PYTHON_EXE%" "%APP_SCRIPT%"

echo.
echo  ================================================================
echo   Application stopped.
echo  ================================================================
echo.
pause
