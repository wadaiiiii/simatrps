import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
export const smartDraft = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: smartDraft.url(args, options),
    method: 'post',
})

smartDraft.definition = {
    methods: ["post"],
    url: '/rps/{rps}/smart-draft',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
smartDraft.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return smartDraft.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
smartDraft.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: smartDraft.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
    const smartDraftForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: smartDraft.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::smartDraft
 * @see app/Http/Controllers/RpsAutomationController.php:14
 * @route '/rps/{rps}/smart-draft'
 */
        smartDraftForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: smartDraft.url(args, options),
            method: 'post',
        })
    
    smartDraft.form = smartDraftForm
/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
export const copyPrevious = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copyPrevious.url(args, options),
    method: 'post',
})

copyPrevious.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/{week}/copy-previous',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
copyPrevious.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return copyPrevious.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
copyPrevious.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copyPrevious.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
    const copyPreviousForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: copyPrevious.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
        copyPreviousForm.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: copyPrevious.url(args, options),
            method: 'post',
        })
    
    copyPrevious.form = copyPreviousForm
/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
export const applyMethod = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyMethod.url(args, options),
    method: 'post',
})

applyMethod.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/apply-method',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
applyMethod.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return applyMethod.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
applyMethod.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
    const applyMethodForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: applyMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
        applyMethodForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: applyMethod.url(args, options),
            method: 'post',
        })
    
    applyMethod.form = applyMethodForm
/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
export const validateObe = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: validateObe.url(args, options),
    method: 'post',
})

validateObe.definition = {
    methods: ["post"],
    url: '/rps/{rps}/validate-obe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
validateObe.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return validateObe.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
validateObe.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: validateObe.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
    const validateObeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: validateObe.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::validateObe
 * @see app/Http/Controllers/RpsAutomationController.php:73
 * @route '/rps/{rps}/validate-obe'
 */
        validateObeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: validateObe.url(args, options),
            method: 'post',
        })
    
    validateObe.form = validateObeForm
const RpsAutomationController = { smartDraft, copyPrevious, applyMethod, validateObe }

export default RpsAutomationController