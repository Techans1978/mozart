/**
 * @param resource
 * @param bpmnFactory
 */
export function createElement(
  resource: Decision,
  bpmnFactory: import("bpmn-js/lib/features/modeling/BpmnFactory").default
): any;
export type Decision = {
    type: "dmnDecision";
    name: string;
    decisionId: string;
};
