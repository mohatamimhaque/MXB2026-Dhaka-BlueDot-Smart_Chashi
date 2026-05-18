@echo off
setlocal EnableDelayedExpansion

set "BASE_DIR=%~dp0"
set "VENV_DIR=%BASE_DIR%venv"
set "PYTHON_EXE=%VENV_DIR%\Scripts\python.exe"
set "REQ_FILE=%BASE_DIR%requirements.txt"
set "BACKEND_SCRIPT=%BASE_DIR%plant_detection_api.py"
set "APP_SCRIPT=%BASE_DIR%app.py"
set "ML_PORT=5000"
set "APP_PORT=8080"
set "MAX_WAIT=20"

set "PYTHONIOENCODING=utf-8"
chcp 65001 >nul 2>&1

title Smart Chashi - Disease Detection System
cls

echo.
echo  ================================================================
echo   Smart Chashi  ^|  AI Disease Detection System
echo  ================================================================
echo   ML Backend    ^|  http://localhost:%ML_PORT%
echo   Web App       ^|  http://localhost:%APP_PORT%
echo  ================================================================
echo.

REM --- Step 1: Check Python ---
echo  [1/5] Checking Python installation...
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
echo  [2/5] Checking virtual environment...
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
echo  [3/5] Checking dependencies...
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

REM --- Step 4: Start ML Backend (port 5000) ---
echo  [4/5] Starting ML Backend on port %ML_PORT%...

for /f "tokens=5" %%p in ('netstat -aon 2^>nul ^| findstr ":%ML_PORT% "') do (
    taskkill /PID %%p /F >nul 2>&1
)

start "Smart Chashi ML Backend" /MIN cmd /c ^
    "set PYTHONIOENCODING=utf-8 & chcp 65001 >nul 2>&1 & title Smart Chashi ML Backend (port %ML_PORT%) & echo. & echo  ML Backend starting - model loads in 30-60s & echo  Do NOT close this window. & echo. & "%PYTHON_EXE%" "%BACKEND_SCRIPT%" & pause"

echo         Waiting for ML Backend to load model...
set /a WAIT_COUNT=0
:POLL_BACKEND
timeout /t 3 /nobreak >nul 2>&1
set /a WAIT_COUNT+=1
"%PYTHON_EXE%" -c "import urllib.request; urllib.request.urlopen('http://localhost:%ML_PORT%/health', timeout=2)" >nul 2>&1
if %errorlevel% equ 0 (
    echo         ML Backend ready at http://localhost:%ML_PORT%
    goto BACKEND_READY
)
if !WAIT_COUNT! lss %MAX_WAIT% (
    set /a ELAPSED=WAIT_COUNT*3
    echo         Still loading... !ELAPSED!s elapsed (max 60s)
    goto POLL_BACKEND
)
echo.
echo  [WARNING] ML Backend did not respond in 60s.
echo  Web app will still start - it has its own built-in ML pipeline.
echo  Check the ML Backend window for errors.
echo.

:BACKEND_READY

REM --- Step 5: Start Web App (port 8080) ---
echo  [5/5] Starting Web App on port %APP_PORT%...
echo.
echo  ================================================================
echo   RUNNING
echo   ML Backend    ^|  http://localhost:%ML_PORT%
echo   Web App       ^|  http://localhost:%APP_PORT%
echo  ================================================================
echo   Press Ctrl+C to stop.
echo  ================================================================
echo.

timeout /t 2 /nobreak >nul 2>&1
start "" "http://localhost:%APP_PORT%"

"%PYTHON_EXE%" "%APP_SCRIPT%"

echo.
echo  ================================================================
echo   Web app stopped.
echo   ML Backend may still run in background.
echo   To stop it: taskkill /IM python.exe /F
echo  ================================================================
echo.
pause
