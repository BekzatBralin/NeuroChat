import sys
import json
import base64
import os
from e2b_code_interpreter import Sandbox

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No code provided"}))
        sys.exit(1)
        
    code_b64 = sys.argv[1]
    try:
        code = base64.b64decode(code_b64).decode('utf-8')
    except Exception as e:
        print(json.dumps({"error": f"Failed to decode base64: {e}"}))
        sys.exit(1)

    try:
        with Sandbox.create() as sandbox:
            execution = sandbox.run_code(code)
            
            output_text = []
            if execution.logs.stdout:
                output_text.append("\n".join(execution.logs.stdout))
            if execution.results:
                for result in execution.results:
                    if result.text:
                        output_text.append(result.text)
            
            images = []
            if execution.results:
                for result in execution.results:
                    # e2b result objects have a 'png' property (or 'jpeg') which contains base64
                    if hasattr(result, 'png') and result.png:
                        images.append({'ext': 'png', 'base64': result.png})
                    elif hasattr(result, 'jpeg') and result.jpeg:
                        images.append({'ext': 'jpeg', 'base64': result.jpeg})
            
            error_text = ""
            if execution.error:
                error_text = f"{execution.error.name}: {execution.error.value}\n{execution.error.traceback}"
            elif execution.logs.stderr:
                error_text = "\n".join(execution.logs.stderr)
            
            res = {
                "text": "\n".join(output_text),
                "error": error_text,
                "images": images
            }
            print(json.dumps(res))
    except Exception as e:
        print(json.dumps({"error": f"Sandbox execution failed: {str(e)}"}))

if __name__ == "__main__":
    main()
