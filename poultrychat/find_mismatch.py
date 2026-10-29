import re

with open('scratch_output.html', 'r', encoding='utf-8') as f:
    html = f.read()

lines = html.split('\n')
stack = []
for i, line in enumerate(lines):
    # simple heuristic: count <div and </div
    opens = line.count('<div')
    closes = line.count('</div')
    for _ in range(opens):
        stack.append(i + 1)
    for _ in range(closes):
        if len(stack) > 0:
            stack.pop()
        else:
            print(f"EXTRA CLOSING DIV AT LINE {i + 1}: {line.strip()}")

if stack:
    print(f"UNCLOSED DIVS OPENED AT LINES: {stack}")
