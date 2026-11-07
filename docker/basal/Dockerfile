# basal-serve: the moderation model on the CPU, in one process through llama.cpp
FROM python:3.12-slim

# the versions from the test in docs/models.md; torch in the CPU build, without CUDA
RUN pip install --no-cache-dir torch==2.15.0 --index-url https://download.pytorch.org/whl/cpu \
    && pip install --no-cache-dir llama-cpp-python==0.3.36 --only-binary llama-cpp-python \
        --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/cpu \
    && pip install --no-cache-dir "basal @ https://github.com/rkinas/basal/archive/refs/tags/v2.4.1.tar.gz"

# downloads the model file on the first start, into the volume, and prints its path; later starts use that file
# --model gives the tokenizer and the calibration, --gguf the weights
CMD gguf="$(hf download "$BASAL_REPO" "$BASAL_FILE" --revision "$BASAL_REVISION" --quiet)" \
    && exec basal-serve --model "$BASAL_REPO" --revision "$BASAL_REVISION" --mode gguf --gguf "$gguf" \
        --device cpu --host 0.0.0.0 --port 8000
