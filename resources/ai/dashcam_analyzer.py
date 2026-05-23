import argparse
import json
import os
import sys
from pathlib import Path


SAFETY_CLASSES = {"car", "truck", "bus", "motorcycle", "bicycle", "person"}


def clamp(value):
    return max(0.0, min(1.0, float(value)))


def load_dependencies():
    try:
        import cv2
        import numpy as np
    except Exception as exc:
        raise RuntimeError("OpenCV and NumPy are required for local dashcam analysis.") from exc

    return cv2, np


def load_yolo(model_path):
    if not model_path or not os.path.isfile(model_path):
        return None

    try:
        from ultralytics import YOLO

        return YOLO(model_path)
    except Exception:
        return None


def yolo_score(model, frame):
    if model is None:
        return 0.0, [], False

    detections = []
    proximity_score = 0.0
    results = model.predict(frame, verbose=False, imgsz=640)
    height, width = frame.shape[:2]
    image_area = max(1, width * height)

    for result in results:
        names = getattr(result, "names", {})

        for box in getattr(result, "boxes", []):
            class_id = int(box.cls[0])
            label = str(names.get(class_id, class_id))
            confidence = float(box.conf[0])

            if label not in SAFETY_CLASSES or confidence < 0.25:
                continue

            x1, y1, x2, y2 = [float(value) for value in box.xyxy[0]]
            area_ratio = max(0.0, ((x2 - x1) * (y2 - y1)) / image_area)
            proximity_score = max(proximity_score, min(1.0, area_ratio * 4.0))
            detections.append(
                {
                    "label": label,
                    "confidence": round(confidence, 2),
                    "area_ratio": round(area_ratio, 4),
                }
            )

    return proximity_score, detections, True


def frame_score(cv2, np, frame, previous_gray, model):
    resized = resize_frame(cv2, frame)
    gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY)
    motion_score = 0.0

    if previous_gray is not None:
        diff = cv2.absdiff(gray, previous_gray)
        motion_score = float(np.mean(diff) / 255.0)

    proximity_score, detections, yolo_enabled = yolo_score(model, resized)
    edge_score = float(np.mean(cv2.Canny(gray, 80, 160)) / 255.0)
    risk_score = clamp((motion_score * 2.2) + (proximity_score * 0.55) + (edge_score * 0.15))
    reasons = []

    if motion_score >= 0.12:
        reasons.append("possible sudden motion or braking")

    if proximity_score >= 0.45:
        reasons.append("close object proximity may indicate elevated risk")

    if any(item["label"] == "person" for item in detections):
        reasons.append("pedestrian detected near the driving scene")

    if edge_score >= 0.22 and motion_score >= 0.08:
        reasons.append("possible unsafe lane change or road-edge movement")

    if risk_score >= 0.7:
        reasons.append("possible collision or near-miss risk")

    return {
        "gray": gray,
        "risk_score": round(risk_score, 2),
        "motion_score": round(clamp(motion_score * 3.0), 2),
        "proximity_score": round(proximity_score, 2),
        "edge_score": round(clamp(edge_score), 2),
        "detections": detections[:8],
        "reasons": reasons,
        "yolo_enabled": yolo_enabled,
        "frame": resized,
    }


def resize_frame(cv2, frame):
    height, width = frame.shape[:2]

    if width <= 960:
        return frame

    target_width = 960
    target_height = int(height * (target_width / width))

    return cv2.resize(frame, (target_width, target_height))


def analyze_image(cv2, np, input_path, output_dir, model):
    frame = cv2.imread(str(input_path))

    if frame is None:
        raise RuntimeError("The image could not be read by OpenCV.")

    scored = frame_score(cv2, np, frame, None, model)
    output_path = Path(output_dir) / "selected-frame-001.jpg"
    cv2.imwrite(str(output_path), scored["frame"])

    return [
        {
            **scored,
            "timestamp_seconds": 0.0,
            "path": str(output_path),
        }
    ]


def analyze_video(cv2, np, input_path, output_dir, max_frames, model):
    capture = cv2.VideoCapture(str(input_path))

    if not capture.isOpened():
        raise RuntimeError("The video could not be opened by OpenCV.")

    fps = capture.get(cv2.CAP_PROP_FPS) or 24
    sample_every = max(1, int(fps * 0.5))
    frame_index = 0
    previous_gray = None
    scored_frames = []

    while True:
        ok, frame = capture.read()

        if not ok:
            break

        if frame_index % sample_every == 0:
            scored = frame_score(cv2, np, frame, previous_gray, model)
            previous_gray = scored["gray"]
            scored["timestamp_seconds"] = round(frame_index / fps, 2)
            scored_frames.append(scored)

        frame_index += 1

        if len(scored_frames) >= 80:
            break

    capture.release()

    if not scored_frames:
        raise RuntimeError("No usable frames were found in the video.")

    selected = sorted(scored_frames, key=lambda item: item["risk_score"], reverse=True)[:max_frames]

    for index, item in enumerate(selected, start=1):
        output_path = Path(output_dir) / f"selected-frame-{index:03d}.jpg"
        cv2.imwrite(str(output_path), item["frame"])
        item["path"] = str(output_path)

    return selected


def build_response(selected_frames):
    risk_score = max(item["risk_score"] for item in selected_frames)
    yolo_enabled = any(item["yolo_enabled"] for item in selected_frames)
    reasons = []
    detections = []

    for item in selected_frames:
        reasons.extend(item["reasons"])
        detections.extend(item["detections"])

    unique_reasons = list(dict.fromkeys(reasons))

    if not unique_reasons:
        unique_reasons = ["no high-risk local indicators detected"]

    confidence_score = 0.72 if yolo_enabled else 0.52

    if risk_score >= 0.7:
        summary = "Local dashcam screening appears to show possible dangerous driving indicators. Manual review recommended."
    elif risk_score >= 0.4:
        summary = "Local dashcam screening may indicate moderate safety risk. Manual review recommended."
    else:
        summary = "Local dashcam screening appears to show no strong high-risk visual event. Manual review recommended if concerns remain."

    response_frames = []

    for item in selected_frames:
        response_frames.append(
            {
                "path": item["path"],
                "timestamp_seconds": item["timestamp_seconds"],
                "score": item["risk_score"],
                "reasons": item["reasons"],
            }
        )

    return {
        "source": "local_opencv_yolo",
        "status": "completed",
        "yolo_enabled": yolo_enabled,
        "risk_score": round(risk_score, 2),
        "confidence_score": round(confidence_score, 2),
        "summary": summary,
        "detected_events": unique_reasons,
        "detections": detections[:20],
        "selected_frames": response_frames,
    }


def main():
    parser = argparse.ArgumentParser(description="Local dashcam risk screening with OpenCV and optional YOLO.")
    parser.add_argument("--input", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--max-frames", type=int, default=3)
    parser.add_argument("--model", default="")
    parser.add_argument("--media-type", choices=["video", "image"], default="video")
    args = parser.parse_args()

    cv2, np = load_dependencies()
    input_path = Path(args.input)
    output_dir = Path(args.output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    model = load_yolo(args.model)

    if args.media_type == "image":
        selected_frames = analyze_image(cv2, np, input_path, output_dir, model)
    else:
        selected_frames = analyze_video(cv2, np, input_path, output_dir, max(1, args.max_frames), model)

    print(json.dumps(build_response(selected_frames)))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"status": "failed", "error": str(exc)}), file=sys.stderr)
        sys.exit(1)
